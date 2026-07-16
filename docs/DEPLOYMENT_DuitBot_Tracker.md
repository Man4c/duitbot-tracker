# Deployment DuitBot Tracker

## Ringkasan (CI/CD)

Deploy production memakai **Render Blueprint** (`render.yaml` di root) dengan runtime **Docker** (`Dockerfile` + folder `docker/`). Database production: **PostgreSQL managed Render** (kode portabel; dev lokal boleh MySQL). Auto-deploy dari branch `main`. CI GitHub Actions (`.github/workflows/tests.yml` + `lint.yml`) jalan di push/PR ke `main`/`develop`.

Konfigurasi saat ini menargetkan **FREE TIER Render** (biaya $0): **satu web service + PostgreSQL gratis**, tanpa worker/cron/preDeployCommand berbayar.

## Proses runtime (free tier)

Satu image Docker, satu service web (`entrypoint web`):

- **Web**: nginx + php-fpm (via supervisor), port 8080, `healthCheckPath: /health`. Aset Vite di-build saat image dibangun.
- **Migrasi**: dijalankan **saat container start** di `docker/entrypoint.sh` (bukan `preDeployCommand` berbayar). Idempotent; free tier = 1 instance jadi tak ada migrasi paralel.
- **Queue**: `QUEUE_CONNECTION=sync` → job (`ProcessTelegramUpdate`, dll.) diproses **langsung dalam request** tanpa worker. Parsing + kirim Telegram <1 detik; Telegram toleran ~60s.
- **Scheduler**: dipicu **eksternal** via `POST /api/cron/run` (diamankan `CRON_SECRET`), bukan Render cron. Lihat bagian "Scheduler eksternal".

> ⚠️ **Batasan free tier:** (1) web **spin-down saat idle** (~15 mnt) → request pertama lambat ~50s; webhook Telegram pertama setelah idle bisa telat, tapi Telegram retry sehingga update tak hilang. (2) PostgreSQL free bisa **kedaluwarsa** (cek kebijakan terkini di dashboard) → perlu re-provision. (3) Kalau volume pesan naik, queue=sync bisa jadi bottleneck → **upgrade ke worker berbayar** (cabang `worker`/`scheduler` sudah tersedia di `entrypoint.sh` & git history).

## Langkah deploy pertama kali

1. Push repo ke GitHub.
2. Render → **New → Blueprint** → pilih repo → Render membaca `render.yaml` (web free + Postgres free).
3. Isi env **secret** (tandai `sync: false`) di dashboard: `APP_KEY` (generate baru: `php artisan key:generate --show`), `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `CRON_SECRET` (bebas, string acak kuat), dan `APP_URL` (isi setelah domain web terbit, format `https://...`).
4. Deploy. Migrasi jalan otomatis saat container start.

## Scheduler eksternal (cron-job.org)

Karena Render cron berbayar, scheduler dipicu layanan cron gratis:

1. Daftar di **cron-job.org** (atau sejenis).
2. Buat job: **URL** `https://<domain>/api/cron/run`, **method** `POST`, **header** `X-Cron-Secret: <CRON_SECRET>` (atau query `?token=<CRON_SECRET>`), **interval** setiap 1 menit.
3. Endpoint menjalankan `schedule:run` → mengevaluasi task due di `routes/console.php` (ReconcileBudgets harian 08:00 WIB, SendWeeklyDigest Minggu 20:00 WIB).

## Environment wajib

Sebagian besar env non-secret sudah diset di `envVarGroups: duitbot-env` dalam `render.yaml` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_LOCALE=id`, `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=database`, `LOG_CHANNEL=stderr`, `TELEGRAM_BOT_USERNAME`). Kredensial DB (`DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`) diinjeksi otomatis dari service `duitbot-db` via `fromDatabase`.

Yang **wajib diisi manual sebagai secret** (`sync: false`) di dashboard Render: `APP_KEY`, `APP_URL` (HTTPS), `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `CRON_SECRET`. Jangan simpan nilai ini di repository.

## Telegram webhook

Daftarkan URL `https://DOMAIN/api/telegram/webhook` melalui method `setWebhook` Telegram Bot API dan sertakan `secret_token` yang sama dengan `TELEGRAM_WEBHOOK_SECRET`. Periksa kembali melalui `getWebhookInfo`. Endpoint readiness tersedia di `/health`.

Untuk development lokal tanpa URL publik, jalankan `php artisan telegram:poll`. Perintah `composer dev` sudah menjalankan polling ini bersama web server, queue worker, dan Vite. Jangan menjalankan polling bersamaan dengan webhook production.

## Uji image Docker secara lokal (opsional, sebelum push)

```bash
docker build -t duitbot .
# Uji cepat container web (butuh env DB; contoh minimal):
docker run --rm -p 8080:8080 \
  -e APP_KEY="base64:..." -e APP_ENV=production -e APP_DEBUG=false \
  -e DB_CONNECTION=pgsql -e DB_HOST=... -e DB_DATABASE=... -e DB_USERNAME=... -e DB_PASSWORD=... \
  duitbot web
# lalu akses http://localhost:8080/health → harapkan {"status":"ready"}
```

Catatan: `route:cache` **tidak** dijalankan (routes memakai closure); hanya `config:cache` + `view:cache`.

Catatan build: file Wayfinder (`resources/js/actions|routes|wayfinder`) di-`.gitignore` sehingga di-generate saat build. Plugin Wayfinder memanggil `php artisan wayfinder:generate` saat `vite build`, jadi stage build Docker menyertakan PHP + composer + Node sekaligus (bukan stage Node murni).

## Runbook: masa aktif database free habis / migrasi DB

PostgreSQL free Render **punya masa aktif terbatas** (verifikasi tanggal di dashboard `duitbot-db`; Render mengirim email peringatan sebelum habis). Kode/config/Docker aman di GitHub — hanya **database** yang perlu diantisipasi. Dua jalur:

### Jalur A — Pindah ke Neon (rekomendasi: gratis & permanen, tak kedaluwarsa)

Neon (https://neon.tech) = PostgreSQL murni gratis-permanen. Cocok karena app sudah pakai `pgsql` & auth sendiri (Telegram OTP) — tak butuh fitur ekstra Supabase. (Alternatif: Supabase juga free, tapi free tier-nya **pause setelah ~7 hari idle** & bawa banyak fitur tak terpakai.)

Langkah:
1. Daftar Neon → **Create project** (region terdekat, mis. Singapore/US) → salin **connection string** (`postgresql://user:pass@ep-xxx.region.aws.neon.tech/dbname?sslmode=require`).
2. Di Render, `DB_*` saat ini **di-inject otomatis dari service `duitbot-db`** via `fromDatabase` di `render.yaml` (lihat blok `envVars` service `duitbot-web`). Untuk pakai Neon, mekanisme itu harus **dilepas** dan `DB_*` diisi manual:
   - **Cara termudah (tanpa ubah kode):** override di env group / service env. Hapus/timpa `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` dengan nilai dari Neon. `DB_CONNECTION=pgsql` tetap. Tambah `DB_SSLMODE=require` (Neon wajib SSL) bila belum ada.
   - **Atau via IaC:** di `render.yaml`, hapus 5 blok `fromDatabase` (baris DB_HOST…DB_PASSWORD) & hapus service `duitbot-db`, lalu set `DB_*` sebagai secret `sync: false`; isi manual di dashboard.
3. **Redeploy** web service. `entrypoint.sh` menjalankan `migrate --force` saat start → tabel dibuat otomatis di Neon (DB kosong). Tak perlu pindah data lama bila data test tak penting.
4. Cek `/health` → `database: ok`. Kirim `/start` + transaksi via bot untuk verifikasi tulis DB.
5. (Opsional) hapus service `duitbot-db` lama di Render setelah yakin Neon jalan.

> Migrasi DATA lama (bila nanti penting): `pg_dump "<render-external-url>" | psql "<neon-url>"`. Untuk saat ini data test tak penting, jadi cukup migrasi struktur via `migrate` (langkah 3).

### Jalur B — Re-provision database free Render (cadangan, harus diulang berkala)

1. Render → **New → PostgreSQL** (free) → beri nama (mis. `duitbot-db-2`).
2. Update referensi: di `render.yaml` ganti semua `fromDatabase: name: duitbot-db` → nama baru (atau timpa `DB_*` manual di dashboard).
3. Redeploy → `migrate` saat start bikin tabel baru (kosong).
4. Hapus DB lama yang kedaluwarsa.

### Catatan

- **Nol perubahan kode** di kedua jalur — app portable (`config/database.php` sudah punya `pgsql`). Yang berubah hanya env `DB_*`.
- **Backup ringan** (bila data mulai penting): `pg_dump` berkala ke file lokal. Selama data belum kritis, tak perlu.
- **Upgrade path**: bila app jadi serius, Render paid (~$7/bln web + ~$7 DB) hilangkan spin-down & expiry + bisa aktifkan worker/scheduler asli (cabang di `entrypoint.sh` sudah siap).

## Checklist rilis

1. Jalankan migration dan pastikan `/health` merespons status `ready`.
2. Pastikan proses worker dan scheduler aktif.
3. Kirim `/start`, transaksi percobaan, `/ringkasan`, dan `/login` melalui bot.
4. Verifikasi dashboard, edit/hapus, grafik, budget, dan instalasi PWA dari origin HTTPS.
5. Pastikan log tidak memuat token, OTP, cookie, atau isi pesan pengguna.
