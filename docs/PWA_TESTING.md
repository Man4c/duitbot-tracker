# Pemeriksaan PWA DuitBot Tracker

## Pemeriksaan lokal di Chrome/Edge desktop

1. Hentikan proses development lama, lalu jalankan kembali `composer dev` agar konfigurasi PWA terbaru dimuat.
2. Buka `http://127.0.0.1:8000` dan lakukan hard refresh (`Ctrl+Shift+R`).
3. Buka DevTools → Application → Manifest. Pastikan nama `DuitBot Tracker`, mode `standalone`, serta ikon 192×192 dan 512×512 terlihat tanpa error.
4. Buka Application → Service Workers. Pastikan service worker berstatus activated/running.
5. Gunakan ikon install pada address bar atau menu browser → Install DuitBot.
6. Setelah terbuka sebagai aplikasi, pastikan jendela tidak memiliki address bar browser dan ikon DuitBot tampil.

## Pemeriksaan offline dasar

1. Dalam DevTools → Network, pilih Offline.
2. Reload halaman atau buka route baru pada origin yang sama.
3. Halaman “Kamu sedang offline” harus tampil dengan tombol “Coba lagi”.
4. Kembalikan Network ke Online, lalu tekan “Coba lagi”.

## Android

Android memerlukan URL HTTPS yang dapat diakses ponsel (localhost komputer tidak sama dengan localhost ponsel). Setelah staging tersedia, buka URL di Chrome Android → menu → Install app/Add to Home screen. Pastikan ikon, nama, splash screen, dan mode standalone benar.

## iOS/iPadOS

Buka URL HTTPS di Safari → Share → Add to Home Screen. Jalankan dari Home Screen dan pastikan ikon serta mode standalone benar. iOS tidak selalu menampilkan prompt instalasi otomatis; pemasangan melalui menu Share adalah perilaku normal.

## Validasi otomatis

Setelah `npm run build`, jalankan `npm run pwa:check`. Pemeriksaan gagal bila manifest kehilangan metadata penting, dimensi ikon salah, maskable icon hilang, atau fallback offline tidak masuk service worker.
