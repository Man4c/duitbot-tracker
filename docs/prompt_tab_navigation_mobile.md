# Prompt: Tab Navigation untuk Dashboard Mobile

Saya ingin memperbaiki pengalaman mobile pada halaman Dashboard. Saat ini di breakpoint mobile, keempat section (Ringkasan Bulan Ini, Pengeluaran Harian/Sebaran Kategori, Riwayat Transaksi, Budget per Kategori) ditumpuk vertikal, sehingga user harus scroll sangat panjang setiap kali ingin berinteraksi dengan Riwayat Transaksi atau Budget.

## Perubahan yang diinginkan

Tambahkan **tab navigation** khusus untuk breakpoint mobile, dengan 3 tab:

1. **Ringkasan** — berisi kartu Total Pengeluaran, Transaksi Tercatat, Sisa Budget, serta grafik Pengeluaran Harian dan Sebaran Kategori (gabungan konten yang saat ini ada di bagian atas dashboard)
2. **Transaksi** — berisi section Riwayat Transaksi lengkap (search, filter, list, pagination)
3. **Budget** — berisi section Budget per Kategori lengkap (form set budget, daftar kategori dengan progress bar)

## Ketentuan Perilaku

- Tab navigation **hanya muncul di breakpoint mobile**. Di desktop/tablet, layout tetap seperti sekarang (semua section ditampilkan sekaligus, tidak perlu tab)
- Tab **"Ringkasan"** menjadi tab aktif default saat halaman pertama dibuka
- State tab aktif cukup disimpan sebagai reactive state lokal di komponen Vue (misal `ref`), **tidak perlu routing terpisah** atau perubahan URL
- Saat berpindah tab, tidak perlu re-fetch data dari API — data yang sudah di-fetch saat halaman dimuat cukup ditampilkan/disembunyikan sesuai tab aktif
- Transisi antar tab cukup instan atau fade halus singkat — hindari animasi berlebihan (sesuai prinsip produk: tidak boleh terasa seperti UI penuh dekorasi/animasi)

## Ketentuan Aksesibilitas (WCAG 2.2 AA)

- Tab menggunakan pattern ARIA yang benar: `role="tablist"`, `role="tab"` untuk tiap tombol tab, `aria-selected`, `role="tabpanel"` untuk konten masing-masing tab
- Navigasi tab bisa dilakukan lewat keyboard (panah kiri/kanan untuk pindah tab, tanpa harus tab lewat seluruh elemen di dalamnya)
- Fokus yang terlihat jelas (focus ring) saat tab dipilih lewat keyboard
- Kontras teks tab aktif vs tidak aktif tetap memenuhi AA

## Ketentuan Visual

- Tab bar menggunakan style flat, minimal border, konsisten dengan desain dashboard yang sudah ada (bukan tab dengan gradient/shadow berlebihan)
- Tab aktif dibedakan dengan latar/border yang jelas, bukan hanya perbedaan warna teks tipis
- Pastikan touch target tab tetap memenuhi minimum 44-48px tinggi untuk kenyamanan tap di mobile

## Yang Tidak Berubah

- Tidak ada perubahan pada logic fetching data, endpoint API, atau struktur komponen di level data/store
- Layout desktop/tablet tidak berubah sama sekali
- Fitur-fitur yang sudah ada di masing-masing section (search, filter, edit, hapus transaksi, set budget) tetap berfungsi persis sama, hanya berpindah lokasi tampilan di mobile

## Setelah Selesai

Tolong jalankan test yang relevan (termasuk test aksesibilitas/keyboard navigation jika ada) untuk memastikan perubahan ini tidak merusak fungsionalitas yang sudah ada, dan konfirmasi ke saya breakpoint pixel yang digunakan untuk membedakan mobile vs desktop.
