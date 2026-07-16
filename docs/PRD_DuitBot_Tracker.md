# Product Requirements Document (PRD)
## DuitBot Tracker — Aplikasi Tracking Pengeluaran Harian via Telegram

**Versi Dokumen:** 1.1
**Tanggal:** 11 Juli 2026 (update status: 13 Juli 2026)
**Status:** Fase 1–3 selesai diimplementasikan (sudah diverifikasi kesesuaiannya dengan kode). Tersisa tahap deployment production — lihat Bagian 10.

---

## 1. Overview Produk

### 1.1 Latar Belakang
Banyak orang kesulitan mencatat pengeluaran harian secara konsisten karena harus membuka aplikasi khusus setiap kali bertransaksi. DuitBot Tracker menyelesaikan masalah ini dengan memungkinkan pencatatan pengeluaran langsung lewat chat Telegram — channel yang sudah familiar dan selalu terbuka di HP pengguna — sambil tetap menyediakan dashboard visual untuk analisis pengeluaran bulanan.

### 1.2 Tujuan Produk
- Memudahkan pencatatan pengeluaran harian tanpa friksi (cukup chat, tanpa buka app terpisah)
- Memberikan insight visual atas pola pengeluaran bulanan
- Membantu pengguna mengontrol pengeluaran lewat fitur budget & notifikasi

### 1.3 Value Proposition
"Catat pengeluaran secepat kirim chat, pantau keuanganmu secepat buka HP."

---

## 2. Target Pengguna & Persona

### 2.1 Target Pengguna
Individu (personal finance) yang ingin mencatat pengeluaran harian dengan cara paling praktis, terutama yang sudah terbiasa menggunakan Telegram.

### 2.2 User Persona

**Persona: "Rian, 27 tahun, Karyawan Swasta"**
- Sering lupa mencatat pengeluaran kecil (jajan, parkir, ongkos)
- Malas buka aplikasi keuangan yang ribet formnya
- Aktif menggunakan Telegram untuk kerja & komunikasi sehari-hari
- Ingin tahu ke mana saja uangnya habis tiap akhir bulan tanpa harus rekap manual

---

## 3. Alur Pengguna (User Flow)

```
1. User mengirim pesan ke Bot Telegram
   → "Makan siang 25000"

2. Bot (via webhook) meneruskan pesan ke Laravel backend

3. Laravel memproses teks:
   - Ekstraksi nominal & deskripsi
   - Klasifikasi kategori otomatis
   - Jika ragu → kirim inline keyboard pilihan kategori ke user

4. Data tersimpan ke database (tabel transactions, terhubung ke user_id)

5. Bot mengirim balasan konfirmasi ke user
   → "✅ Tercatat: Makan siang - Rp25.000 - Kategori: Makanan"

6. User membuka Dashboard (PWA):
   - Login via Telegram (OTP dikirim bot)
   - Melihat ringkasan grafik, riwayat transaksi, dan status budget
```

---

## 4. Rincian Fitur & Prioritas

Fitur dikelompokkan berdasarkan fase pengembangan (MVP → lanjutan), menggunakan prioritas **Must-have** dan **Nice-to-have**.

### FASE 1 — Fondasi: Autentikasi & Pencatatan via Chat
> Catatan teknis: meskipun pada tool perencanaan awal fase ini muncul di urutan berikutnya, secara teknis fitur autentikasi dan input chat harus dibangun lebih dulu karena semua fitur lain bergantung padanya.

**4.1 Autentikasi Pengguna** *(Must-have)*
| Sub-fitur | Deskripsi | Prioritas |
|---|---|---|
| Hubungkan Telegram | User register otomatis saat pertama chat bot (Telegram user ID sebagai identitas unik) | Must-have |
| Akses Data Pribadi | Login ke dashboard via OTP dikirim bot Telegram | Must-have |

**4.2 Catat via Chat** *(Must-have)*
| Sub-fitur | Deskripsi | Prioritas |
|---|---|---|
| Input Pengeluaran | Parsing teks bebas ("Bensin 50rb") menjadi nominal + deskripsi | Must-have |
| Konfirmasi Cerdas | Klasifikasi kategori otomatis + inline keyboard jika ambigu | Must-have |
| Cek Ringkasan Cepat | Command `/ringkasan`, `/total`, `/hari_ini` | Nice-to-have |

### FASE 2 — Riwayat & Ringkasan

**4.3 Riwayat Transaksi** *(Must-have)*
| Sub-fitur | Deskripsi | Prioritas |
|---|---|---|
| Daftar Transaksi | List seluruh transaksi dengan tanggal & kategori | Must-have |
| Filter & Cari | Filter berdasarkan tanggal/kategori | Must-have |
| Ubah Transaksi | Edit/hapus transaksi yang salah input | Must-have |

**4.4 Ringkasan Bulanan** *(Must-have)*
| Sub-fitur | Deskripsi | Prioritas |
|---|---|---|
| Grafik Pengeluaran Harian | Bar/line chart pengeluaran per hari | Must-have |
| Bagan Kategori | Pie chart proporsi pengeluaran per kategori | Must-have |
| Perbandingan Bulan | Total bulan ini vs bulan lalu | Nice-to-have |

### FASE 3 — Kontrol Keuangan

**4.5 Anggaran & Notifikasi** *(Nice-to-have)*
| Sub-fitur | Deskripsi | Prioritas |
|---|---|---|
| Set Anggaran | User menentukan budget bulanan per kategori | Nice-to-have |
| Peringatan Chat | Bot mengirim notifikasi saat mendekati/melebihi budget | Nice-to-have |

---

## 5. Arsitektur Sistem

### 5.1 Diagram Alur Teknis
```
[Telegram User]
      |
      | (pesan chat)
      v
[Telegram Bot API] --webhook--> [Laravel Backend]
                                       |
                          +------------+------------+
                          |                         |
                   [MySQL Database]         [REST API Endpoints]
                                                     |
                                                     v
                                          [Vue.js PWA Dashboard]
```

### 5.2 Tech Stack
| Layer | Teknologi |
|---|---|
| Backend | Laravel (PHP) — REST API + webhook handler |
| Frontend | Vue.js + `vite-plugin-pwa` (installable, service worker) |
| Database | MySQL |
| Bot | Telegram Bot API |
| Hosting/Deployment | Render (1 server untuk backend, frontend build disajikan dari server yang sama) |
| Autentikasi API | Laravel Sanctum mode SPA — session/cookie-based + CSRF (bukan Bearer token), sesuai keputusan same-origin di Panduan Arsitektur §4.1 |

### 5.3 Contoh Endpoint API (Laravel)
| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/telegram/webhook` | Menerima pesan masuk dari Telegram |
| POST | `/api/auth/telegram-login` | Verifikasi OTP dari Telegram |
| GET | `/api/transactions` | Ambil daftar transaksi (dengan filter query params) |
| PUT | `/api/transactions/{id}` | Edit transaksi |
| DELETE | `/api/transactions/{id}` | Hapus transaksi |
| GET | `/api/summary/monthly` | Data ringkasan bulanan (untuk grafik) |
| POST | `/api/budgets` | Set anggaran per kategori |
| GET | `/api/budgets/status` | Cek status anggaran (untuk trigger notifikasi) |

### 5.4 Struktur Database (Ringkas)
- **users**: id, telegram_id, name, created_at
- **transactions**: id, user_id, amount, category, description, source (chat), created_at
- **budgets**: id, user_id, category, monthly_limit

---

## 6. Pertimbangan Teknis Khusus

### 6.1 Integrasi Telegram Webhook + Laravel
- Backend Laravel harus memiliki URL publik HTTPS (wajib untuk `setWebhook` Telegram Bot API)
- Gunakan route khusus (contoh: `/api/telegram/webhook`) yang menerima update dari Telegram dan memverifikasi keaslian request (misal cek secret token)
- Proses parsing teks bisa dimulai dengan pendekatan sederhana (regex untuk nominal + keyword matching kategori), lalu ditingkatkan ke NLP/AI jika akurasi kurang

### 6.2 Setup PWA (Vue.js)
- Gunakan `vite-plugin-pwa` untuk generate `manifest.json` dan service worker otomatis
- Pastikan HTTPS aktif (wajib untuk PWA installable)
- Sertakan ikon app (berbagai ukuran) dan `theme_color` di manifest
- Uji fitur "Add to Home Screen" di Android & iOS (catatan: dukungan push notification PWA di iOS terbatas, sehingga notifikasi budget sebaiknya tetap dikirim via bot Telegram sebagai jalur utama)

### 6.3 Keamanan
- Data transaksi setiap user terisolasi (validasi `user_id` di setiap query, bukan hanya di frontend)
- Token API (Sanctum) memiliki masa berlaku dan bisa di-revoke
- Webhook Telegram divalidasi agar tidak bisa dipanggil sembarang pihak

---

## 7. Metrik Keberhasilan (Success Metrics)
- Jumlah transaksi tercatat per user per minggu (indikator adopsi kebiasaan mencatat)
- Rasio user yang membuka dashboard PWA minimal 1x/bulan
- Waktu rata-rata dari pesan terkirim hingga bot membalas konfirmasi (target: di bawah 2 detik)
- Jumlah user yang mengaktifkan fitur budget (indikator engagement fitur lanjutan)

---

## 8. Keputusan Teknis (Klarifikasi Pra-Implementasi)

Berikut keputusan resmi untuk pertanyaan teknis yang muncul sebelum development dimulai:

| Area | Keputusan |
|---|---|
| **Mekanisme login dashboard** | Menggunakan OTP yang dikirim via bot Telegram (lebih sederhana dibanding Telegram Login Widget yang membutuhkan setup domain terverifikasi) |
| **Kategori baku** | 7 kategori standar untuk MVP: Makanan, Transport, Belanja, Tagihan, Hiburan, Kesehatan, Lainnya. **Update implementasi:** diperluas menjadi 24 kategori (termasuk Pendidikan, Investasi, Asuransi, dll) untuk mengurangi transaksi yang jatuh ke "Lainnya". Ini pengayaan yang disengaja, bukan penyimpangan — 7 kategori inti tetap menjadi acuan utama, lihat Bagian 11 |
| **Format nominal yang didukung** | Parsing fleksibel mendukung format: `25rb`, `25k`, `25.000`, `25000`, `25 ribu` (menggunakan regex/normalisasi angka) |
| **Zona waktu** | WIB (Asia/Jakarta) sebagai default untuk seluruh timestamp |
| **Transaksi tanggal lampau** | Di luar scope MVP. MVP hanya mencatat transaksi real-time (hari ini). Input tanggal lampau menjadi fitur nice-to-have di fase berikutnya |
| **Pemasukan, transfer, transaksi berulang** | Di luar scope MVP. Fokus awal murni pencatatan pengeluaran manual satuan. Bisa dikembangkan di fase lanjutan |
| **Koreksi transaksi via Telegram** | MVP: koreksi (edit/hapus) hanya dilakukan lewat dashboard PWA. **Update implementasi:** fitur nice-to-have ini sudah dibangun — command `/hapus` (hapus transaksi terakhir) dan `/ubah <detail>` (koreksi tanpa buka dashboard), lihat Bagian 11 |
| **Kebijakan mata uang** | Hanya Rupiah (IDR), tidak ada dukungan multi-currency di MVP |
| **Deployment database & background job** | MySQL di-deploy sebagai managed database add-on di Render. Proses background (misal pengecekan & pengiriman notifikasi budget) dijalankan lewat queue worker terpisah di Render (Laravel Queue + scheduler) |

---

## 9. Roadmap Ringkas
| Fase | Fitur | Estimasi |
|---|---|---|
| Fase 1 | Autentikasi Telegram + Input via Chat | 2-3 minggu |
| Fase 2 | Riwayat Transaksi + Ringkasan Bulanan (Dashboard PWA) | 2-3 minggu |
| Fase 3 | Anggaran & Notifikasi | 1-2 minggu |

---

## 10. Status Implementasi (Verifikasi Kode vs PRD)

Verifikasi kesesuaian project dengan PRD sudah dilakukan (13 Juli 2026). Ringkasan hasil:

| Fase | Status | Catatan |
|---|---|---|
| Fase 1 — Autentikasi & Pencatatan via Chat | ✅ Selesai | Register otomatis via Telegram ID, login OTP (6 digit, 5 menit, sekali pakai, maks 5 percobaan), parsing nominal, klasifikasi kategori + inline keyboard, command `/ringkasan`, `/total`, `/hari_ini` |
| Fase 2 — Riwayat & Ringkasan | ✅ Selesai | Daftar transaksi + pagination, filter & cari, edit/hapus, grafik harian (Chart.js), pie chart kategori, perbandingan bulan |
| Fase 3 — Kontrol Keuangan | ✅ Selesai | Set anggaran per kategori, peringatan chat di ambang 80%/100% (dicek setelah transaksi + rekonsiliasi scheduler harian) |
| Aspek teknis (keamanan, isolasi data, PWA, idempotency, queue/scheduler) | ✅ Selesai | Sesuai ketentuan di `ARCHITECTURE_DuitBot_Tracker.md` §4 |

**Kesimpulan:** seluruh fitur must-have dan nice-to-have pada PRD sudah diimplementasikan, dengan beberapa bagian melebihi scope awal (lihat Bagian 11).

### 10.1 Yang Tersisa (Bukan Masalah Kesesuaian, Melainkan Tahap Operasional)
- [ ] Deployment ke Render (web service + queue worker + scheduler + managed MySQL)
- [ ] Daftarkan webhook HTTPS production & matikan mode polling (jika dipakai saat development)
- [ ] Verifikasi instalasi PWA di perangkat fisik (butuh HTTPS staging/production)
- [ ] Rotasi token Telegram Bot via BotFather sebelum go-live (lihat pengingat keamanan yang sudah dibahas sebelumnya)
- [ ] Instrumentasi tracking untuk metrik produk di Bagian 7

---

## 11. Fitur Tambahan (Di Luar Scope MVP Awal)

Selama pengembangan, beberapa fitur tambahan dibangun di atas fondasi MVP. Ini bersifat pengayaan, bukan penyimpangan dari PRD, dan tidak melanggar prinsip produk yang sudah ditetapkan (praktis, ramah, terpercaya).

| # | Fitur | Deskripsi |
|---|---|---|
| 1 | Multi-transaksi dalam satu pesan | User bisa kirim beberapa transaksi sekaligus (misal "Kopi 20rb, parkir 5rb, bensin 50rb"), bot membalas rekap + total gabungan |
| 2 | Koreksi via chat | `/hapus` untuk menghapus transaksi terakhir, `/ubah <detail>` untuk koreksi tanpa perlu buka dashboard |
| 3 | Ringkasan mingguan otomatis | Bot mengirim rekap tiap Minggu pukul 20:00 WIB — 7 hari terakhir + kategori dengan pengeluaran terbesar |
| 4 | `/ringkasan` diperkaya | Selain total, sekarang juga menampilkan kategori terbesar, persentase, dan proyeksi pengeluaran akhir bulan |
| 5 | Kamus kategori diperluas | ~90 keyword tambahan (indomaret, seblak, indihome, paylater, dll) untuk mengurangi transaksi yang salah masuk kategori "Lainnya" |
| 6 | Metrik produk | Command `metrics:product` — mengukur pengguna aktif, transaksi/minggu, aktivitas buka dashboard, aktivasi budget, dan waktu proses pesan (≤2 detik, sesuai target di Bagian 7) |

### 11.1 Keputusan Teknis Terkait Fitur Tambahan
- **Skema `telegram_update_id`**: constraint unique diubah menjadi index biasa (lewat migrasi baru) agar satu pesan Telegram bisa menghasilkan lebih dari satu transaksi (kasus multi-transaksi). Idempotency tetap dijaga lewat pola *delete-then-create* saat retry
- **Privasi metrik produk**: hanya mencatat durasi proses dalam milidetik, bukan isi pesan pengguna — konsisten dengan ketentuan privasi log di `ARCHITECTURE_DuitBot_Tracker.md` §4.3
- **Kompatibilitas**: seluruh fitur bot tetap backward compatible — transaksi tunggal dan inline keyboard yang sudah ada berfungsi persis seperti sebelumnya

---

*Dokumen ini dapat disesuaikan lebih lanjut sesuai temuan selama proses development, terutama terkait akurasi parsing teks otomatis dan preferensi UX dashboard.*