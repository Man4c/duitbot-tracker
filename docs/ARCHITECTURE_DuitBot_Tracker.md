# Architecture & Development Guide
## DuitBot Tracker

**Versi Dokumen:** 1.0
**Tanggal:** 11 Juli 2026
**Status:** Siap untuk implementasi
**Dokumen terkait:** `PRD_DuitBot_Tracker.md` (rujuk dokumen ini untuk detail fitur, user flow, dan keputusan produk)

---

## 1. Prinsip Arsitektur

Project ini menggunakan **satu project Laravel (monorepo)**, dengan Vue.js terintegrasi di dalamnya melalui Vite — **bukan** dua folder/repo backend dan frontend yang terpisah.

Alasan pendekatan ini:
- Setup dan deployment lebih sederhana (satu server, satu pipeline build)
- Laravel dan Vue berada dalam satu repository, memudahkan version control
- Tidak perlu mengatur CORS antar-domain
- Autentikasi menggunakan Sanctum SPA (cookie-based) lebih aman dan mudah dibanding token API terpisah
- Satu layanan hosting saja dibutuhkan (sesuai keputusan PRD: deploy di Render)

---

## 2. Struktur Folder Project

```
DuitBot/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/     # TransactionController, AuthController, TelegramWebhookController, dll
│   │   ├── Requests/            # Form Request untuk validasi tiap endpoint
│   │   └── Resources/           # API Resource untuk format response JSON
│   ├── Models/
│   ├── Policies/                # Otorisasi akses resource milik user
│   ├── Services/                # Business logic: TelegramService, ParsingService, BudgetService
│   └── Jobs/                    # Background job, misal notifikasi budget
├── routes/
│   ├── web.php                  # Route utama yang serve aplikasi Vue (SPA entry)
│   └── api.php                  # API privat + webhook stateless; lihat batas middleware di bagian 4
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── resources/
│   ├── js/                      # Aplikasi Vue (lihat detail di bagian 3)
│   ├── css/
│   └── views/                   # Cukup 1 blade file sebagai entry point SPA
├── public/                      # Hasil build otomatis dari Vite
├── tests/
│   ├── Feature/                 # Test tiap endpoint API
│   └── Unit/                    # Test service/parsing logic
├── composer.json
├── package.json
└── vite.config.js
```

---

## 3. Struktur Frontend (`resources/js`)

```
resources/js/
├── components/       # Komponen reusable: TransactionCard, BudgetProgress, ChartSummary, dll
├── views/             # Halaman: Dashboard, TransactionList, Login, Settings
├── composables/       # Logic reusable: useAuth, useTransactions, useBudget
├── services/          # API client, axios instance + endpoint wrapper
├── stores/            # Pinia untuk state management: authStore, transactionStore
├── router/            # Vue Router + route guard untuk halaman yang butuh login
└── app.js             # Entry point
```

### Ketentuan Frontend
| Aspek | Keputusan |
|---|---|
| Framework | Vue 3 + Vite (satu build pipeline dengan Laravel) |
| PWA | `vite-plugin-pwa`, dikonfigurasi langsung di `vite.config.js` (manifest.json + service worker) |
| State management | Pinia |
| Styling | Tailwind CSS |
| Autentikasi | Laravel Sanctum SPA authentication (cookie-based, bukan token API terpisah). Vue dan Laravel wajib disajikan dari origin yang sama |

---

## 4. Ketentuan Backend

- Gunakan **Service Layer pattern**: controller hanya menangani request/response, logic bisnis (parsing nominal, klasifikasi kategori, cek budget) ditaruh di `app/Services`
- `TelegramWebhookController` dibuat terpisah khusus untuk menangani webhook
- `ParsingService` khusus untuk ekstraksi nominal & kategori dari teks pesan
- Background job (notifikasi budget) menggunakan **Laravel Queue** (`database` driver cukup untuk tahap awal)
- Pengecekan berkala menggunakan **Laravel Scheduler**

### 4.1 Batas Route, Session, dan Sanctum

- Dashboard Vue dan Laravel disajikan dari **origin yang sama**. Bila arsitektur deployment berubah menjadi lintas-origin, konfigurasi CORS dan Sanctum stateful domain wajib ditinjau ulang.
- Login OTP dan logout menggunakan session Laravel melalui middleware `web`. Session ID wajib diregenerasi setelah login dan diinvalidate saat logout.
- Endpoint privat dashboard dilindungi middleware `auth:sanctum` dan mengandalkan cookie session `HttpOnly`, `Secure` di production, serta `SameSite=Lax`.
- Vue mengambil CSRF cookie sebelum mengirim request login atau request lain yang mengubah state.
- Webhook Telegram bersifat stateless, tidak menggunakan session/CSRF, dan dilindungi secret token Telegram.
- Route autentikasi boleh diletakkan di `web.php` dengan prefix `/api/auth`, atau di file route khusus yang secara eksplisit memakai middleware `web`. Jangan mengandalkan middleware stateless bawaan `api.php` untuk login berbasis session.

### 4.2 Keamanan OTP

- OTP hanya dapat diminta oleh user yang `telegram_id`-nya sudah terdaftar melalui interaksi dengan bot.
- OTP dibuat dengan generator acak yang aman, disimpan dalam bentuk **hash**, berlaku maksimal **5 menit**, dan hanya dapat digunakan satu kali.
- Pembuatan OTP baru otomatis membatalkan seluruh OTP aktif sebelumnya milik user tersebut.
- Verifikasi dibatasi maksimal **5 percobaan** per OTP. Setelah batas tercapai, OTP dinonaktifkan.
- Endpoint permintaan dan verifikasi OTP wajib memakai rate limiting berdasarkan kombinasi IP, user, dan/atau identifier login.
- Respons permintaan OTP tidak boleh membocorkan apakah suatu akun/Telegram ID terdaftar.
- OTP, token bot, secret webhook, cookie, dan kredensial lain tidak boleh ditulis ke application log.

### 4.3 Webhook Telegram yang Aman dan Idempotent

- Webhook wajib memverifikasi header secret token Telegram menggunakan perbandingan yang aman.
- Setiap update dicatat berdasarkan `telegram_update_id` yang memiliki unique index. Update yang sudah pernah diterima tidak boleh membuat transaksi kedua.
- Webhook memvalidasi bentuk update, mencatat penerimaan secara atomik, lalu mengembalikan respons secepat mungkin. Pekerjaan yang berpotensi lambat diproses melalui queue.
- Job pemrosesan update juga wajib idempotent agar retry queue tidak menggandakan transaksi atau balasan bot.
- Pesan yang tidak didukung harus menghasilkan respons yang aman tanpa mencatat transaksi parsial.
- Log hanya menyimpan metadata yang diperlukan dan tidak menyimpan isi pesan atau data sensitif tanpa kebutuhan operasional yang jelas.

### 4.4 Isolasi dan Otorisasi Data

- Seluruh query transaksi, budget, dan ringkasan wajib di-scope ke user yang sedang login; jangan mengambil resource hanya berdasarkan `{id}` global.
- Gunakan Laravel Policy dan/atau akses melalui relasi user, misalnya `$request->user()->transactions()`.
- Kepemilikan resource diverifikasi di backend untuk operasi baca, ubah, dan hapus. Route guard di Vue bukan kontrol keamanan.
- Feature test wajib membuktikan bahwa user tidak dapat membaca, mengubah, atau menghapus resource milik user lain.

### 4.5 Aturan Data Inti

- `users.telegram_id` menggunakan tipe integer 64-bit yang sesuai dan unique index.
- `transactions.amount` disimpan sebagai integer/big integer dalam satuan Rupiah; dilarang memakai `float` atau `double` untuk nilai uang.
- Waktu transaksi disimpan di kolom `occurred_at`, terpisah dari `created_at`. Pada MVP nilainya adalah waktu saat pesan valid diproses.
- Timestamp database disimpan secara konsisten dalam UTC dan dikonversi ke `Asia/Jakarta` saat ditampilkan atau dihitung sebagai hari/bulan pengguna. Ini menjaga portabilitas tanpa mengubah keputusan produk bahwa zona waktu MVP adalah WIB.
- Kategori MVP menggunakan tujuh nilai baku dari PRD. Implementasi harus memiliki satu sumber kebenaran (enum/value object atau tabel referensi), bukan string bebas yang tersebar di kode.
- `telegram_updates.telegram_update_id` wajib unique. Transaksi yang berasal dari Telegram menyimpan referensi update sumber bila relevan.
- Tambahkan index gabungan yang mendukung query utama, minimal `(user_id, occurred_at)` dan index untuk filter kategori per user.
- Foreign key dan aturan penghapusan harus eksplisit. Penghapusan user menghapus atau menganonimkan data terkait sesuai kebijakan retensi yang dipilih sebelum production.

### 4.6 Notifikasi Budget

- Pemeriksaan budget utama dijalankan setelah transaksi berhasil dicatat; scheduler digunakan untuk rekonsiliasi, bukan satu-satunya pemicu.
- Peringatan dikirim maksimal satu kali untuk setiap ambang dalam satu periode, minimal ambang **80%** dan **100%**.
- Simpan status/riwayat notifikasi dengan unique constraint yang mencakup user, kategori, periode, dan ambang agar retry job atau scheduler tidak mengirim duplikat.
- Periode budget mengikuti bulan kalender dalam zona waktu `Asia/Jakarta` dan status ambang efektif kembali kosong saat memasuki periode baru.

---

## 5. Konvensi & Kualitas Kode

| Area | Ketentuan |
|---|---|
| Backend | PSR-12, gunakan Laravel Pint untuk formatting otomatis |
| Frontend | ESLint + Prettier, konfigurasi standar Vue 3 |
| Validasi API | Wajib menggunakan Form Request pada endpoint yang menerima input terstruktur; validasi payload webhook dilakukan oleh validator khusus sesuai bentuk update Telegram |
| Response sukses | Gunakan API Resource/Resource Collection agar representasi data konsisten |
| Response error | Seragamkan validation, authentication, authorization, not found, dan exception melalui exception handler/middleware JSON; API Resource bukan penangan error |
| Commit message | Mengikuti Conventional Commits (`feat:`, `fix:`, `chore:`, `refactor:`, dll) |

### Ketentuan Testing Minimum

- Unit test untuk normalisasi nominal (`25rb`, `25k`, `25.000`, `25000`, `25 ribu`) dan klasifikasi tujuh kategori.
- Feature test untuk alur OTP, expiry, one-time use, rate limit, dan batas percobaan.
- Feature test untuk verifikasi secret webhook, update duplikat, retry job, serta pesan invalid.
- Feature test isolasi data antarpengguna untuk list, detail, edit, hapus, summary, dan budget.
- Test batas tanggal/bulan menggunakan zona waktu `Asia/Jakarta`.

---

## 6. Alur Development & Deployment

**Development (lokal):**
- Jalankan `php artisan serve` dan `npm run dev` (Vite) secara bersamaan
- Laravel tetap melayani halaman dan API, sedangkan Vite development server menyediakan asset dan HMR melalui integrasi Laravel Vite. Jika proxy khusus diperlukan, konfigurasinya harus ditulis eksplisit di `vite.config.js`
- Jalankan queue worker saat menguji webhook/job: `php artisan queue:work`
- Jalankan scheduler secara lokal saat menguji pekerjaan berkala: `php artisan schedule:work`

**Production/Deployment:**
- Jalankan `npm run build` — hasil build masuk ke `public/build`
- Laravel serve semuanya dari satu domain
- Sediakan web service Laravel untuk HTTP, worker service untuk `php artisan queue:work`, dan cron/scheduler untuk `php artisan schedule:run`
- Jalankan migration sebagai release/deploy step yang terkontrol sebelum instance baru menerima traffic
- Tetapkan retry, timeout, dan `failed_jobs` untuk queue; job yang mengirim pesan Telegram wajib idempotent
- Sediakan endpoint health check untuk koneksi aplikasi, database, dan kesiapan queue yang relevan
- Setelah URL production aktif, daftarkan webhook Telegram HTTPS beserta secret token dan verifikasi status webhook
- Konfigurasi production (`APP_KEY`, database, bot token, webhook secret, session, dan queue) disimpan sebagai environment secret, bukan di repository
- Walaupun aplikasi disajikan dari satu domain, deployment terdiri dari beberapa proses runtime: web, queue worker, dan scheduler

---

## 7. Urutan Pengerjaan

Pengerjaan mengikuti roadmap fase pada PRD. Untuk **setiap fase**, ikuti urutan berikut agar development terstruktur dan mudah diuji:

1. Buat migration & model
2. Buat Service + Controller + Route
3. Uji endpoint dengan Feature test sebelum lanjut ke frontend
4. Integrasikan ke komponen Vue terkait

### Fase 1 — Fondasi
- Setup project Laravel + Vue (via Laravel Starter Kit jika tersedia, atau instalasi manual)
- Migrasi database awal
- Autentikasi Telegram (OTP)
- Webhook, parsing pesan, penyimpanan transaksi, dan balasan konfirmasi melalui bot Telegram (tidak ada form input transaksi di dashboard pada MVP)

### Fase 2 — Riwayat & Ringkasan
- Riwayat transaksi (list, filter, edit, hapus)
- Ringkasan bulanan (grafik)

### Fase 3 — Kontrol Keuangan
- Anggaran & notifikasi budget

---

## 8. Sebelum Mulai Coding

Coding agent/developer wajib mengonfirmasi hal berikut sebelum mulai implementasi:
1. Apakah setup awal menggunakan **Laravel Starter Kit (Vue)** bawaan atau instalasi manual
2. Daftar package tambahan yang akan diinstall (backend & frontend), untuk direview terlebih dahulu
3. Struktur project yang sudah ada, versi Laravel/PHP/Node, serta package yang telah terpasang agar tidak melakukan setup ulang
4. Strategi tabel kategori (enum/value object atau tabel referensi) dan kebijakan retensi saat user dihapus sebelum migration difinalkan

---

*Dokumen ini digunakan bersama `PRD_DuitBot_Tracker.md`. PRD menjelaskan **apa** yang dibangun (fitur, user flow, keputusan produk), dokumen ini menjelaskan **bagaimana** cara membangunnya (struktur, konvensi, urutan teknis).*
