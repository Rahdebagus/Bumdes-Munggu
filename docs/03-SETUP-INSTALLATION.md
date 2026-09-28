# 03 — Instalasi proyek baru pada PHP 8.2.12

Panduan ini untuk membangun aplikasi dari blueprint. Perintah belum dijalankan untuk membangun aplikasi saat dokumen diserahkan. Jalankan dari VS Code Terminal/PowerShell atau terminal setara; gunakan folder aplikasi baru.

## 1. Konfigurasi yang digunakan

Gunakan **PHP CLI 8.2.12**, **Composer 2.6.6**, dan **Laravel 12.x**. PHP dan Composer sudah tersedia pada komputer pengguna. Semua paket aplikasi dan pengujian harus mendukung konfigurasi ini. Periksa executable serta ekstensi yang aktif sebelum instalasi dependency.

Frontend: Blade + Livewire3 + Tailwind4 + Alpine bawaan Livewire. Database target: **MariaDB 10.4.32** (bundel XAMPP), berjalan di 127.0.0.1:3306 (diverifikasi 28 Sep 2026). Ini engine resmi proyek — bukan MySQL 8.x. Karena engine berbeda, gunakan koneksi driver `mariadb` bawaan Laravel 12 (`DB_CONNECTION=mariadb`), jangan `mysql`, agar collation default `utf8mb4_unicode_ci` (bukan `utf8mb4_0900_ai_ci` khas MySQL 8 yang tidak ada di MariaDB) dan grammar MariaDB dipakai. Arahkan DB_HOST/DB_PORT ke instance MariaDB tersebut. Persiapan database tidak mengubah PHP. Lihat §MariaDB 10.4 di bawah untuk detail kompatibilitas.

Verifikasi di terminal pengguna, bukan mengklaim runtime lingkungan agen sama dengan komputer pengguna:

```bat
where php
php -v
php --ini
php -m
where composer
composer --version
node --version
npm --version
git --version
```

Perintah versi Composer adalah `composer --version` atau `composer -V` (V besar). `composer 0v` salah ketik. Gunakan executable PHP 8.2.12 yang sudah terdeteksi; lokasi XAMPP harus diperiksa, jangan diasumsikan. Ekstensi yang diperlukan mencakup PDO MySQL (`pdo_mysql`; ekstensi ini juga dipakai untuk MariaDB), mbstring, openssl, fileinfo, DOM/XML, cURL dan ZIP untuk XLSX. Bila kurang, aktifkan pada php.ini yang ditunjuk `php --ini`, tanpa mengganti versi PHP.

Node/npm belum diketahui versinya. Pilih tooling frontend yang cocok dengan runtime aktual. Bila PowerShell memblokir npm.ps1, gunakan npm.cmd/npx.cmd atau Command Prompt.

## 2. Scaffold Laravel 12 yang dikunci

Gunakan satu jalur instalasi: skeleton Laravel12 dengan constraint versi eksplisit, lalu integrasikan Blade/Livewire/Tailwind dan autentikasi. Agen wajib menyelesaikan integrasi frontend/auth di tahap01; skeleton saja belum aplikasi siap login.

Di folder induk aplikasi, bukan di folder dokumentasi yang sudah berisi file:

```bat
composer create-project laravel/laravel bumdes-munggu "12.*" --no-install --no-scripts
cd bumdes-munggu
composer config platform.php 8.2.12
```

Sebelum install, periksa composer.json: `require.php` harus mengizinkan8.2.12, `laravel/framework` harus `^12.0`. Semua require-dev juga harus kompatibel. Gunakan PHPUnit11 sebagai runner default; jangan memasukkan PHPUnit12 atau Pest4 yang menuntut runtime lebih tinggi. Jika template sudah memiliki dev dependency berbeda, sesuaikan constraint ke versi kompatibel sebelum resolution.

```bat
composer require "laravel/framework:^12.0" "livewire/livewire:^3.0" "laravel/fortify:^1.0" --no-update
composer require --dev "phpunit/phpunit:^11.0" --no-update
composer install
composer check-platform-reqs
php artisan --version
```

Constraint `^12.0` mencegah perpindahan major; `config.platform.php=8.2.12` membatasi pemilihan dependency terhadap PHP pengguna. Lockfile menyimpan versi konkret. `check-platform-reqs` memeriksa runtime/ekstensi nyata, bukan hanya nilai platform simulasi. Jika resolver gagal, baca paket penghalang dan pilih release kompatibel. Jangan memakai `--ignore-platform-reqs`, menaikkan platform ke PHP fiktif, atau mengubah target PHP sebagai jalan pintas.

Karena create-project memakai --no-scripts, pastikan `.env` tersedia (salin `.env.example` jika belum ada) dan generate APP_KEY setelah dependency terpasang. Jangan membuat ulang APP_KEY pada aplikasi yang sudah berjalan.

### Integrasi Blade, Livewire dan autentikasi — tahap01

```bat
php artisan fortify:install
php artisan make:livewire Dashboard/Index
npm install
```

Pilih Livewire3.x untuk konsistensi seluruh API dalam blueprint. Composer harus memilih patch3.x yang kompatibel dengan Laravel12/PHP8.2.12; catat versi konkret. Gunakan dokumentasi3.x, jangan mencampur contoh versi lain.

Copilot wajib menyelesaikan:

1. FortifyServiceProvider terdaftar satu kali di bootstrap/providers.php. Fortify loginView mengembalikan `view('auth.login')`; nonaktifkan registration pada config/fortify.php.
2. View login Blade memakai form POST ke route login, @csrf, email/password, error validation dan remember. Logout memakai form POST+CSRF. Atur autentikasi untuk menolak user nonaktif, rate limit, regenerasi session dan logout yang benar. Role/status baru dibuat migration sebelum pengujian login.
3. `resources/views/components/layouts/app.blade.php` untuk full-page Livewire dengan `{{ $slot }}`, @vite, @livewireStyles dan @livewireScripts. Layout guest terpisah untuk login. Livewire menyediakan Alpine; jangan memasang CDN Alpine atau memanggil Alpine.start() kedua kali.
4. Dashboard component merender `livewire.dashboard.index` memakai layout app; route GET /dashboard ke class tersebut, middleware auth dan EnsureUserActive.
5. Komponen Blade reusable di resources/views/components/ui dengan props dan attribute bag. Form bisnis memakai Livewire Form object, wire:model, wire:submit, @error, wire:loading. Semua mutation method authorize ulang.
6. CSS Tailwind4 dan Vite disiapkan. Pada skeleton Laravel12, periksa package.json/vite.config.js/resources/css/app.css dulu; jangan menggandakan plugin. Jika belum ada, tambahkan tailwindcss4+@tailwindcss/vite4 dan konfigurasi plugin tailwindcss() bersama laravel(). Gunakan `@import "tailwindcss"` dan pastikan source Blade, Livewire pagination, serta JS ter-scan.
7. File resources/js/app.js untuk helper/chart saja; bukan entrypoint framework frontend. Script npm dev/build harus sesuai Vite; pilih versi Vite/plugin yang mendukung Node terpasang, lalu commit package-lock.json.
8. Daftarkan middleware status pengguna sebagai persistent middleware Livewire dan uji request update setelah akun dinonaktifkan. Endpoint download/export tetap Controller Laravel dengan Policy.

Jika Tailwind belum tersedia:

```bat
npm install -D "tailwindcss@^4" "@tailwindcss/vite@^4"
```

Jangan memasang template frontend lain. Tampilan dashboard dibuat dengan Blade+Tailwind; komponen paket shadcn/ui tidak diinstal. Desain memakai card/sidebar/table/dialog yang konsisten.

Salin docs, .github/copilot-instructions.md, dan PROGRESS.md ke root aplikasi. Buka folder aplikasi sebagai workspace VS Code setelah scaffold selesai. Tahap01 baru selesai setelah login, dashboard, interaksi Livewire dan build berjalan.

## 3. Database lokal

Login sebagai administrator database (XAMPP: `C:\xampp\mysql\bin\mysql.exe -u root`, password default kosong). Buat database dan akun aplikasi. Ganti nilai password contoh melalui kredensial lokal, jangan commit nilainya.

Catatan status lokal (28 Sep 2026): database `bumdes_munggu` **sudah ada dan kosong** dengan collation server default `utf8mb4_general_ci`; `bumdes_munggu_test` belum ada. Karena itu perintah memakai `IF NOT EXISTS` (tidak menghapus/menimpa data) dan `ALTER DATABASE` untuk menyelaraskan collation ke `utf8mb4_unicode_ci`. **Jangan** `DROP DATABASE` atau `migrate:fresh` pada `bumdes_munggu`.

```sql
CREATE DATABASE IF NOT EXISTS bumdes_munggu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER DATABASE bumdes_munggu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'bumdes_app'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_LOKAL';
GRANT ALL PRIVILEGES ON bumdes_munggu.* TO 'bumdes_app'@'localhost';
CREATE DATABASE IF NOT EXISTS bumdes_munggu_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON bumdes_munggu_test.* TO 'bumdes_app'@'localhost';
FLUSH PRIVILEGES;
```

Hak luas tersebut untuk development. Production pisahkan akun migrasi dan akun runtime dengan hak minimum. Bila MariaDB memakai host berbeda, atur host/grant secara spesifik. (Sintaks `CREATE USER IF NOT EXISTS` didukung MariaDB 10.4.)

Isi `.env` lokal, jangan commit:

```dotenv
APP_NAME="SIA BUMDes Munggu"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bumdes_munggu
DB_USERNAME=bumdes_app
DB_PASSWORD=GANTI_PASSWORD_LOKAL
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

Tetapkan zona waktu tampilan bisnis Asia/Makassar, simpan timestamp audit UTC. Tanggal transaksi adalah SQL DATE lokal, bukan konversi UTC tengah malam. Tambahkan konfigurasi `business_timezone` eksplisit. Disk bukti `private` harus dibuat dengan root `storage/app/private`, visibility private.

```bash
php artisan key:generate
php artisan config:clear
php artisan migrate
npm run build
composer run dev
```

Buka http://localhost:8000. `composer run dev` digunakan jika script tersedia dan sudah dikonfigurasi. Jika script tidak tersedia, jalankan `php artisan serve`, `npm run dev`, dan worker queue di terminal masing-masing. Jangan menjalankan worker ganda tanpa sengaja.

### MariaDB 10.4 — konfigurasi koneksi dan kompatibilitas

Engine resmi proyek adalah **MariaDB 10.4.32** (XAMPP). Runtime terverifikasi: `VERSION()=10.4.32-MariaDB`, `sql_mode` global XAMPP = `NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION` (longgar, tanpa `STRICT_TRANS_TABLES`), `innodb_default_row_format=dynamic`, collation server default `utf8mb4_general_ci`. Sesuaikan `config/database.php`:

- **Gunakan koneksi `mariadb`** (Laravel 12 punya driver first-class). Set `DB_CONNECTION=mariadb`. Driver `mysql` memakai default collation `utf8mb4_0900_ai_ci` (khusus MySQL 8) yang **tidak ada** di MariaDB dan akan menggagalkan migration.
- Pada blok `mariadb`: `'charset' => 'utf8mb4'`, `'collation' => 'utf8mb4_unicode_ci'`, `'engine' => 'InnoDB'`.
- **Pertahankan `'strict' => true`** (default Laravel). Ini menetapkan sql_mode sesi ketat (`STRICT_TRANS_TABLES` dll.) per koneksi meski sql_mode global XAMPP longgar — wajib untuk integritas nilai keuangan (cegah truncation diam-diam). Jangan andalkan sql_mode global.

Kompatibilitas skema/kueri untuk dokumen04/05 (semua sudah didukung MariaDB 10.4):

- **CHECK constraint ditegakkan** sejak MariaDB 10.2.1, jadi CHECK debit/kredit XOR, alokasi target XOR, dsb. berlaku nyata. CHECK antar-baris (SUM debit=kredit) tetap tugas PostingService, sama seperti MySQL.
- **Generated column untuk unique key** (mis. `scope_unit_id = COALESCE(unit_usaha_id,0)`): gunakan **STORED/PERSISTENT**, bukan VIRTUAL, agar unique index andal di MariaDB 10.4.
- **Kolom `json`** (snapshot_data, nilai_lama/baru audit, result_ref, metadata): MariaDB menyimpan `JSON` sebagai `LONGTEXT` + `CHECK (json_valid(col))`, bukan tipe biner MySQL. Baca/tulis dokumen utuh via Eloquent cast (`array`/`json`) aman; operator path `->`/`->>` didukung (10.2.3+), tetapi jangan mengandalkan perilaku indexing JSON biner khas MySQL 8.
- **UNIQUE membolehkan beberapa NULL** (sama seperti MySQL) — pola opening rincian tetap valid.
- `innodb_default_row_format=dynamic` → prefiks index panjang aman; **tidak perlu** `Schema::defaultStringLength(191)`.
- Backup memakai `mysqldump` (tersedia di XAMPP) atau `mariadb-dump`; keduanya kompatibel.

## 4. Sesuaikan auth dengan tabel laporan

Skeleton Laravel memakai `users.name`; laporan memakai `users.nama`. Gunakan kolom **nama** secara konsisten: migration, model, registration action, factory, seeder, view Blade dan form Livewire, dan pengujian. Disable registrasi publik setelah bootstrap; buat command `app:create-user` dengan password prompt tersembunyi. Jangan seed password tetap ke production.

Tambahkan `role` (admin/pimpinan), `status` (aktif/nonaktif), middleware status, dan Policy. `remember_token`, `email_verified_at`, kolom 2FA jika digunakan adalah kolom framework tambahan, bukan perubahan kebutuhan bisnis.

## 5. Paket tambahan bertahap

Gunakan decimal library seperti `brick/math`; PDF melalui adapter Dompdf; XLSX melalui adapter PhpSpreadsheet. Sebelum install, Copilot harus memeriksa kompatibilitas paket terhadap PHP dan Laravel yang benar-benar terpasang, kemudian mencatat versi lockfile. Jangan menulis pemanggilan API paket berdasarkan ingatan jika major-nya berbeda.

```bash
composer require brick/math
composer require barryvdh/laravel-dompdf
composer require phpoffice/phpspreadsheet

```

Semua instalasi paket tambahan tetap berjalan dengan config.platform.php=8.2.12. Pilih versi yang menerima PHP tersebut, termasuk dependency transitif dan dev tools. Jangan instal ulang paket yang sudah tersedia. Jika resolver menolak, periksa kompatibilitas dan catat keputusan; jangan gunakan `--ignore-platform-reqs` untuk melewati masalah.

## 6. Setup setelah modul tersedia

```bash
php artisan migrate
php artisan db:seed --class=MasterDataSeeder
php artisan app:create-user
php artisan test
npm run build
```

`MasterDataSeeder` dan `app:create-user` adalah deliverable tahap implementasi, belum perintah bawaan Laravel. Seeder master hanya unit/kode akun/mapping; DemoSeeder terpisah dan menolak production.

Buat `.env.testing` dengan database `bumdes_munggu_test`. Pastikan tes tidak pernah mengarah ke database production. Jalankan `php artisan view:cache`, `php artisan test`, dan `npm run build`. Pemeriksaan format PHP dapat memakai Pint jika terpasang. Tidak diperlukan script typecheck frontend; gunakan tes komponen Livewire dan browser.

## 7. Kriteria setup selesai

- `php -v` tetap8.2.12, `php artisan --version` menunjukkan12.x, dan `composer check-platform-reqs` lulus.
- Login lokal berhasil; akun nonaktif ditolak; logout mengakhiri session.
- Pimpinan tidak dapat mengakses mutation Admin lewat URL atau request langsung.
- Dashboard Blade/Livewire tampil responsif tanpa console error.
- MariaDB (driver `mariadb`) digunakan, migration bersih, frontend production build berhasil.
- Lockfile, `.env.example` tanpa rahasia, dan dokumentasi cara menjalankan tersedia.
- Data laporan kosong menampilkan nol/empty state secara jujur.

## Referensi

- [Laravel installation](https://laravel.com/docs/12.x/installation)
- [Blade Laravel12](https://laravel.com/docs/12.x/blade)
- [Livewire3 installation](https://livewire.laravel.com/docs/3.x/installation)

Referensi diperiksa 28 September 2026. Detail dependency mengikuti lockfile proyek yang dihasilkan.

## Referensi kompatibilitas

- [Laravel12 release notes dan dukungan PHP](https://laravel.com/docs/12.x/releases)
- [Fortify untuk Laravel12](https://laravel.com/docs/12.x/fortify)

Perintah dan integrasi di atas belum dijalankan pada komputer pengguna; agen lokal harus memverifikasinya. Target verifikasi adalah Laravel12 berjalan pada PHP8.2.12 dengan dependency dan ekstensi yang sesuai.
