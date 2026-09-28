# Instruksi Copilot — SIA BUMDes Munggu full Laravel

Mulai proyek baru dari tahap00. Baca PROGRESS.md, dokumen modul terkait dan kode aktual. Selesaikan satu tahap end-to-end, kemudian update progres dengan bukti pengujian.

## Stack tetap

PHP8.2.12, Composer2.6.6, Laravel12.x, Blade, Livewire3.x, Tailwind4, Alpine bawaan Livewire, MariaDB10.4.32 (XAMPP, port3306), PHPUnit11. Set config.platform.php8.2.12 dan pin laravel/framework^12.0 serta livewire/livewire^3.0. Gunakan koneksi database driver `mariadb` (Laravel12), bukan `mysql`, agar collation default `utf8mb4_unicode_ci` dan grammar MariaDB dipakai. Semua paket utama/transitif/dev harus kompatibel. Gunakan setup dokumen03, jangan template latest tanpa pin atau --ignore-platform-reqs.

Frontend dan backend berada dalam satu aplikasi Laravel. Blade+Livewire membentuk halaman. Vite hanya membangun CSS/JS pendukung. Tidak memasang React, Inertia, TypeScript, atau shadcn CLI. shadcn/ui hanya referensi visual; implementasikan komponen Blade/Tailwind sendiri. Alpine sudah dibundel Livewire, jangan dimuat dua kali.

## Struktur dan tanggung jawab

app/Livewire/{Module} untuk komponen PHP; resources/views/livewire untuk view; resources/views/components/ui dan shared untuk UI reusable. Livewire Form objects memvalidasi form, Policy mengecek hak, Action domain mengerjakan bisnis, Query membentuk dataset, Eloquent menangani relasi/cast. Controller dipakai untuk download/export/HTTP khusus. FormRequest untuk HTTP biasa; jangan mengasumsikan FormRequest otomatis berjalan pada public method Livewire.

Setiap mutation Livewire authorize ulang dan reload record server. Jangan percaya public property untuk status, role, nominal resmi, actor atau ownership. #[Locked] bukan pengganti policy. Middleware user aktif harus melindungi request update juga.

## Sumber dan database

PDF terbaru mencakup pinjaman/angsuran dan penjualan kredit. Pertahankan18 tabel inti dokumen04 +4 pendukung dokumen05. Jurnal berasal transaksi_detail; buku besar/laporan query, bukan tabel ledger duplikat. users.nama, bukan name. Gunakan FK/CHECK/unique dan tes MariaDB nyata (MariaDB10.4 menegakkan CHECK sejak10.2.1). Pertahankan `'strict' => true` pada koneksi agar sql_mode sesi ketat meski sql_mode global XAMPP longgar.

## Invariant

Decimal server untuk uang. Draft tanpa efek, posted immutable, koreksi reverse. Original reversed tetap dihitung pada tanggalnya bersama pembalik. Posting satu DB transaction+lock+idempotency. Stok tidak negatif dan cache tidak diedit langsung. Moving average dan cost snapshot; larang backdate stok serta reverse dependen sesuai dokumen08. Pokok pinjaman/pelunasan/modal bukan omzet. Saldo cutover tidak ditambah tiap bulan. Snapshot approved immutable dan revisi menjadi versi baru.

## Kualitas

Session+CSRF, registrasi publik nonaktif, bukti private, validasi upload, secrets tidak di-commit. Jangan migrate:fresh pada data nyata. Jangan menyatakan tahap selesai jika baru skeleton atau masih TODO pada alur inti. Tes domain dengan PHPUnit, form/actions dengan Livewire::test, interaksi Alpine dengan browser. Jalankan php artisan view:cache, php artisan test, npm run build; Pint bila tersedia. Laporkan hasil aktual, bukan PASS fiktif.

Setiap akhir tahap tulis fitur selesai, file inti, tes yang dijalankan, hasil, blocker, dan next task konkret dalam PROGRESS.md. Perubahan bisnis harus dicatat pada decision register. Keputusan rutin ikuti blueprint tanpa berulang meminta pilihan stack.
