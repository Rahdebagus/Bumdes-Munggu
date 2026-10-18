# 14 — Deployment, pemulihan, dan serah terima

Target rancangan: VPS Linux dengan Nginx/PHP-FPM/MariaDB 10.4+, HTTPS, worker queue, scheduler. Samakan versi MariaDB dev/staging/production agar perilaku CHECK/collation konsisten. Blade dirender oleh PHP; Livewire membutuhkan endpoint update aktif. Tailwind/JS dibuild menjadi asset Laravel dan aplikasi membutuhkan hosting PHP, bukan hosting statis. Provider spesifik belum ditentukan.

## 1. Sebelum deploy

Catat PHP/Node/MariaDB/dependency aktual dan build SHA. CI: composer install, npm ci, test dengan MariaDB, pemeriksaan Blade dan tes Livewire, build. Siapkan database kosong dan user berhak minimum. Pisahkan akun migration dengan runtime bila tersedia. Siapkan domain+TLS, volume persisten untuk private storage, serta secret environment.

Production environment minimal:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN_ANDA
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
DB_CONNECTION=mariadb
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

APP_KEY dibuat sekali dan disimpan aman; jangan generate ulang pada setiap release. Kredensial DB dan key tidak masuk repository atau log CI. Cek cookie domain dan trusted proxy sesuai server; jangan menonaktifkan CSRF untuk memperbaiki419.

## 2. Release

Gunakan direktori release terpisah dan shared `.env`/storage. Nginx document root hanya `<release>/public`. PHP-FPM socket harus sesuai PHP yang dipasang. Gunakan konfigurasi resmi Laravel sebagai dasar dan tambahkan TLS sesuai server. Jangan expose project root, `.env`, vendor, atau storage/app/private.

Urutan contoh pada release baru setelah environment terpasang:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Jalankan migrate setelah backup berhasil dan perubahan schema ditinjau. Atomic switch symlink ke release baru setelah smoke internal, atau gunakan maintenance singkat sesuai strategi. Build frontend dapat dilakukan di CI dan asset dikirim tanpa node_modules. `queue:restart` membutuhkan process manager yang otomatis menjalankan worker kembali. Tidak menjalankan `composer run dev` di production.

Command bootstrap user/seeder production dijalankan sekali dengan input yang dikonfirmasi, bukan DemoSeeder. Jika MasterDataSeeder menggunakan akun contoh, review mapping sebelum mengaktifkannya pada BUMDes nyata.

Bootstrap data pada release pertama (setelah migrate, sekali saja):

```bash
# Isi SEED_ADMIN_*/SEED_PIMPINAN_*/SEED_PERIODE_AWAL di shared .env lebih dulu.
php artisan db:seed --class=ProductionSeeder --force
```

`ProductionSeeder` memuat master data (unit usaha + chart of accounts ber-system_key), akun login Admin & Pimpinan, dan periode go-live — tanpa satu pun transaksi/pihak/produk contoh. Konfigurasi divalidasi lebih dahulu, sehingga `.env` yang belum lengkap berhenti tanpa meninggalkan database separuh terisi, dan seeder aman dijalankan ulang (akun yang sudah ada tidak diubah). Seeder juga memperingatkan bila menemukan akun demo (`*.demo@...`) — tanda database UAT, bukan produksi.

Segera setelah bootstrap: kosongkan kembali `SEED_ADMIN_PASSWORD` & `SEED_PIMPINAN_PASSWORD` di `.env`, jalankan `php artisan config:clear`, serahkan password awal lewat kanal terpisah, dan minta pengelola meninjau nama unit/penanggung jawab serta chart of accounts sebelum saldo awal dikunci.

## 3. Worker dan scheduler

Process manager menjalankan `php artisan queue:work --tries=3 --timeout=120` (sesuaikan waktu ekspor dan retry_after agar tidak duplicate job). Scheduler: cron setiap menit `php /path/to/artisan schedule:run`. Posting finansial tidak di-queue; ekspor boleh queue setelah commit dan idempotent.

Gunakan user OS aplikasi, izin tulis hanya storage dan bootstrap/cache; jangan chmod777 seluruh repository. Status worker, queue gagal, ruang disk, error5xx dan umur backup dipantau. Log berotasi dan tidak memuat data pribadi yang tidak perlu.

## 4. Backup

Target awal [ASUMSI]: RPO24jam, RTO4jam; pengelola menetapkan ulang jika kebutuhan lebih ketat. Backup harian DB InnoDB dengan snapshot konsisten, private evidence, snapshot PDF/XLSX resmi, dan konfigurasi/key terpisah terenkripsi. Simpan copy di lokasi berbeda dan uji retensi misalnya7harian+4mingguan+6bulanan sesuai kebutuhan data.

Gunakan file kredensial DB berizin600; jangan password pada command line. Contoh backup DB memakai credential file yang telah disiapkan administrator:

```bash
# MariaDB: mysqldump tersedia; mariadb-dump adalah alias resmi pada rilis MariaDB
mysqldump --defaults-extra-file=/secure/bumdes-backup.cnf --single-transaction --routines --triggers bumdes_munggu > bumdes_munggu.sql
```

Koordinasikan snapshot file dan database: file bukti immutable, backup seluruh file yang direferensikan dump; jangan membersihkan file yang masih direferensikan. Catat manifest SHA256 dan timestamp. Backup sukses bukan bukti restore berhasil.

## 5. Restore drill

1. Buat environment terisolasi, bukan menimpa production.
2. Restore DB, private files, dan environment/key dari backup yang sama.
3. Jalankan versi kode yang kompatibel dengan schema backup.
4. Cek jumlah transaksi, debit-kredit, saldo kontrol, snapshot hash dan sampel bukti.
5. Jalankan login dua role, buku besar, enam laporan dan unduh file.
6. Catat durasi, timestamp backup, hasil rekonsiliasi, masalah dan tindak lanjut.

## 6. Rollback

Rollback kode ke release sebelumnya hanya jika schema kompatibel. Migration destructive tidak otomatis di-rollback dengan `migrate:rollback` karena dapat kehilangan data. Gunakan expand/contract schema dan migration koreksi maju. Restore backup dipilih hanya dengan keputusan pemilik data karena transaksi setelah backup dapat hilang; dokumentasikan cutover dan rekonsiliasi.

## 7. Smoke test setelah deploy

Login Admin/Pimpinan, akses tanpa role ditolak, dashboard periode benar, upload/download bukti privat, preview/post satu fixture staging, export PDF/XLSX, worker berjalan, snapshot approval, close/reopen staging, dan HTTPS. Jangan memasukkan transaksi demo ke production untuk tes; gunakan staging atau transaksi riil yang diotorisasi pengelola.

## 8. Serah terima

- Panduan instalasi dan menjalankan lokal.
- Panduan Admin: master, opening, draft/post/reverse, transaksi dagang/kredit, stok/aset, bukti, laporan.
- Panduan Pimpinan: dashboard, audit, approval, close/reopen, export resmi.
- Data dictionary/migration, daftar policy dan konfigurasi akuntansi.
- Catatan UAT/SUS aktual, known issues, versi release dan daftar dependency.
- Runbook backup/restore, kontak pengelola, prosedur ganti password dan insiden.
- Checklist kesesuaian CDM/PDM laporan dengan22 tabel implementasi usulan.

Referensi resmi: [Laravel deployment](https://laravel.com/docs/12.x/deployment). Detail web server menyesuaikan lingkungan yang benar-benar disediakan.

## Kompatibilitas runtime

Bangun dan uji dengan Laravel12 dan platform.php8.2.12. Pipeline jangan memilih dependency dari runtime PHP yang lebih tinggi lalu menghasilkan lockfile yang gagal pada komputer pengguna. Jalankan composer check-platform-reqs pada runtime target. Runtime hosting harus menjalankan dependency yang sudah diuji; periksa platform requirements pada server target.

## Verifikasi Livewire pada production

Cek asset /livewire/livewire.js, request POST /livewire/update, CSRF/session, temporary upload dan middleware user aktif. Nginx tidak boleh mengembalikan404 untuk script Livewire hanya karena URL berakhiran.js; pastikan routing framework terjangkau. Jalankan view cache pada release baru dan restart worker. Hapus file public/hot sisa development jika ikut deployment agar Vite memakai manifest hasil build. Tes satu filter/tombol Livewire, upload privat dan dialog Alpine setelah release.
