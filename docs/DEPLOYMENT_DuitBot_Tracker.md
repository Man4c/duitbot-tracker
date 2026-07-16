# Deployment DuitBot Tracker

## Ringkasan (CI/CD)

Deploy production memakai **Render Blueprint** (`render.yaml` di root) dengan runtime **Docker** (`Dockerfile` + folder `docker/`). Database production: **PostgreSQL managed Render** (kode portabel; dev lokal boleh MySQL). Auto-deploy dari branch `main`. CI GitHub Actions (`.github/workflows/tests.yml` + `lint.yml`) jalan di push/PR ke `main`/`develop`.

> ⚠️ **Plan berbayar Render:** background worker, cron job, dan `preDeployCommand` memerlukan paid plan. Web service + PostgreSQL punya free tier (dengan batasan idle-sleep & masa DB). Sesuaikan `plan:` di `render.yaml` sebelum deploy.

## Proses runtime

Satu image Docker dipakai tiga service (command dibedakan via `entrypoint <role>`):

- **Web** (`type: web`, `entrypoint web`): nginx + php-fpm (via supervisor), port 8080, `healthCheckPath: /health`. Aset Vite di-build saat image dibangun.
- **Worker** (`type: worker`, `entrypoint worker`): `php artisan queue:work --tries=3 --timeout=30 --sleep=3 --max-time=3600`.
- **Scheduler** (`type: cron`, `schedule: "* * * * *"`, `entrypoint scheduler`): `php artisan schedule:run` tiap menit.
- **Release** (`preDeployCommand` di service web): `php artisan migrate --force` — jalan sebelum versi baru menerima trafik.

## Langkah deploy pertama kali

1. Push repo ke GitHub (project ini belum di-`git init`; lakukan lebih dulu).
2. Render → **New → Blueprint** → pilih repo → Render membaca `render.yaml`.
3. Isi env **secret** (tandai `sync: false`) di dashboard: `APP_KEY` (generate baru: `php artisan key:generate --show`), `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, dan `APP_URL` (isi setelah domain web terbit, format `https://...`).
4. Deploy. `preDeployCommand` akan menjalankan migrasi otomatis.

## Environment wajib

Sebagian besar env non-secret sudah diset di `envVarGroups: duitbot-env` dalam `render.yaml` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_LOCALE=id`, `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=database`, `LOG_CHANNEL=stderr`, `TELEGRAM_BOT_USERNAME`). Kredensial DB (`DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`) diinjeksi otomatis dari service `duitbot-db` via `fromDatabase`.

Yang **wajib diisi manual sebagai secret** (`sync: false`) di dashboard Render: `APP_KEY`, `APP_URL` (HTTPS), `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`. Jangan simpan nilai ini di repository.

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

## Checklist rilis

1. Jalankan migration dan pastikan `/health` merespons status `ready`.
2. Pastikan proses worker dan scheduler aktif.
3. Kirim `/start`, transaksi percobaan, `/ringkasan`, dan `/login` melalui bot.
4. Verifikasi dashboard, edit/hapus, grafik, budget, dan instalasi PWA dari origin HTTPS.
5. Pastikan log tidak memuat token, OTP, cookie, atau isi pesan pengguna.
