Tahap berikutnya sebaiknya dikerjakan dalam urutan ini:
1. Selesaikan pengalaman lokal
- [x] Pagination riwayat transaksi (10 data per halaman, mendukung lebih dari 50 data).
- [x] Loading, empty state, retry error, dan notifikasi aksi yang lebih jelas.
- [x] Perbaiki branding Laravel yang masih terlihat pada judul halaman.
- [ ] Rotasi token Telegram yang sempat terlihat — tindakan manual melalui BotFather diperlukan, lalu token baru dipasang ke `.env`.

2. Lengkapi PWA
- [x] Ikon PNG 192×192, 512×512, Apple Touch, favicon, dan maskable 512×512.
- [x] Validasi otomatis manifest, dimensi ikon, service worker, dan fallback offline.
- [x] Mode offline dasar dengan halaman fallback responsif.
- [ ] Verifikasi instalasi pada perangkat Android/iOS fisik — memerlukan origin HTTPS yang dapat diakses perangkat; lakukan saat URL staging/deployment tersedia.

3. Tambahkan metrik produk
Jumlah transaksi mingguan.
Aktivitas pembukaan dashboard.
Pengguna yang mengaktifkan budget.
Durasi pemrosesan pesan Telegram tanpa mencatat isi pesan sensitif.

4. Persiapan production
Konfigurasi Render untuk web, queue worker, dan scheduler.
Managed MySQL.
Environment production dan secure session cookie.
Health check dan migration release.

5. Deployment dan Telegram production
Deploy ke Render.
Daftarkan webhook HTTPS.
Matikan polling pada production.
Uji alur lengkap /start → catat transaksi → OTP → dashboard → budget notification.
