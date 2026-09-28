# 05 — Penyempurnaan database untuk implementasi

**Semua isi dokumen ini [TEKNIS]/[ASUMSI], bukan salinan schema PDF.** Baseline 18 tabel tetap ada. Blueprint operasional memakai 18 + 4 tabel bisnis pendukung di bawah. Perubahan ini harus tercermin pada revisi CDM/PDM jika laporan akademik dituntut identik dengan implementasi. Jangan mengklaim 22 tabel berasal dari laporan.

## Mengapa perlu diperluas

| Celah baseline | Dampak jika dibiarkan | Keputusan |
|---|---|---|
| angsuran hanya punya satu transaksi_id | Pembayaran sebagian kedua menimpa referensi pertama | pembayaran_angsuran menyimpan setiap alokasi |
| Piutang/utang dagang tanpa alokasi invoice | Tidak tahu invoice mana sudah dibayar | alokasi_pelunasan |
| Stok hanya cache/hasil transaksi | Reverse dan saldo awal sulit diaudit per mutasi | mutasi_stok immutable |
| Saldo awal hanya per akun | Piutang, stok, aset, pinjaman tidak cocok dengan rincian | saldo_awal_rincian |
| Metode pembayaran, jatuh tempo, diskon/pajak belum ada | Form di laporan tidak dapat disimpan utuh | Kolom tambahan eksplisit |
| Bunga tidak menyebut dasar periode | Total bunga dapat berbeda 12 kali lipat | basis_bunga wajib |
| Belum ada lock/idempotency/revision | Klik ganda dapat menggandakan jurnal | unique key dan version checks |

## Perluasan kolom inti

Nama snake_case; semua FK memakai BIGINT UNSIGNED; semua tambahan wajib memiliki migration tersendiri. N = DECIMAL(19,2), Q = DECIMAL(18,4), C = DECIMAL(19,6). Perluasan D ke N pada nominal adalah keputusan kapasitas, bukan konversi ke float.

| Tabel | Tambahan / perubahan |
|---|---|
| users | remember_token S(100)?, email_verified_at timestamp? bila dipakai auth; password tetap hash |
| periode_akuntansi | opening_locked_at/by nullable; reopened_at/by nullable; reopen_reason TEXT?; revision BIGINT default 0; lock saldo awal berlaku periode |
| akun | system_key S(50)? UQ (cash, bank, inventory, trade_receivable, loan_receivable, trade_payable, capital, sales, interest_income, penalty_income, cogs, inventory_loss, inventory_gain, fixed_asset, accumulated_depreciation, depreciation_expense, retained_earnings, tax_input, tax_output, operating_expense); report_group S(50); is_cash BOOLEAN |
| saldo_awal_akun | scope_unit_id generated COALESCE(unit_usaha_id,0); saldo memakai N; locked_at/by? untuk jejak per batch; source_batch UUID? |
| produk | harga_pokok_rata C default 0; stok_terkini/minimum Q; stock_version BIGINT default 0 |
| transaksi | metode_pembayaran S(20)? tunai/kredit; akun_kas_id FK akun?; akun_lawan_id FK akun?; kategori_kode S(50)?; jatuh_tempo DATE?; subtotal/diskon_total/pajak_total N default 0; request_key UUID UQ; request_hash CHAR(64); version BIGINT default 1; reversal_reason TEXT?; reversal_date DATE?; policy_version S(30); posting_key S(100)? UQ |
| transaksi_detail | kelompok_arus_kas S(20)? operasi/investasi/pendanaan; report_group_snapshot S(50); nomor_baris integer; UQ(transaksi_id,nomor_baris) |
| transaksi_barang | kuantitas Q; harga_pokok C; harga N; diskon N default 0; pajak N default 0; nilai_pokok N default 0; subtotal N; nomor_baris integer; UQ(transaksi_id,nomor_baris) |
| pinjaman | basis_bunga S(30) flat_total/flat_bulanan; tanggal_angsuran_pertama DATE; policy_version S(30); opening_rincian_id FK saldo_awal_rincian? UQ |
| angsuran | denda_tagihan N default 0; denda_dibayar N default 0; version BIGINT default 1 |
| aset_tetap | akun_aset_id, akun_akumulasi_id, akun_beban_id FK akun; tanggal_mulai_susut DATE; transaksi_perolehan_id FK transaksi?; akumulasi_awal N default 0; opening_rincian_id FK saldo_awal_rincian? UQ |
| bukti_transaksi | original_name S(255); mime S(100); size_bytes BIGINT; sha256 CHAR(64); verified_at?; rejection_reason TEXT?; created_at TS |
| laporan_desa | created_by FK users; version integer; previous_version_id FK laporan_desa?; UQ(periode_id,version); data_revision BIGINT; snapshot_hash CHAR(64); submitted_at TS?; generated_at TS?; scope unit pada snapshot, laporan resmi konsolidasi |
| log_aktivitas | request_key UUID? UQ; request_hash CHAR(64)?; result_ref JSON? untuk idempotency transition, bukan data sensitif |
| stok_opname | approved_at TS?; counted_at TS?; submitted_at TS?; revision BIGINT default 1 |
| stok_opname_detail | stock_version_snapshot BIGINT; kuantitas Q; harga_pokok C; nilai_selisih N |

Seluruh tabel mutable mempunyai created_at/updated_at bila belum ada. Immutable detail boleh hanya created_at. Jangan menambahkan soft delete ke jurnal untuk menyembunyikan efek finansial.

Kategori disediakan dalam `config/accounting.php` sebagai mapping berversi; kolom kategori_kode menyimpan kodenya, bukan label. Kategori mencakup jasa, modal_masuk, modal_keluar, beban, pelunasan_piutang, pelunasan_utang, perolehan_aset. Akun lawan dibatasi per kategori. Perubahan mapping tidak menulis ulang transaksi lama.

## Tabel pendukung 1: pembayaran_angsuran

| Field | Tipe / aturan |
|---|---|
| id | B PK |
| transaksi_id | B FK transaksi jenis angsuran |
| angsuran_id | B FK angsuran |
| pokok, bunga, denda | N >=0; total >0 |
| reversal_of_id | B? FK pembayaran_angsuran UQ |
| created_at | TS |

UQ(transaksi_id,angsuran_id). Satu transaksi dapat membayar beberapa jadwal; satu jadwal dapat menerima banyak transaksi. Baris pembalik membawa nilai positif sama dengan sumber, tanda kontribusi -1 diturunkan dari transaksi.reversal_of_id. Jangan menjumlah tanpa arah reversal. Total alokasi harus sama dengan debit kas. Cache angsuran diperbarui setelah posting atomik dan dapat direkonsiliasi dari tabel ini.

## Tabel pendukung 2: alokasi_pelunasan

| Field | Tipe / aturan |
|---|---|
| id | B PK |
| pembayaran_transaksi_id | B FK transaksi pemasukan/pengeluaran |
| invoice_transaksi_id | B? FK transaksi penjualan/pembelian kredit atau pengeluaran perolehan aset kredit |
| opening_rincian_id | B? FK saldo_awal_rincian piutang/utang |
| nominal | N >0 |
| reversal_of_id | B? FK alokasi_pelunasan UQ |
| created_at | TS |

CHECK tepat satu target invoice_transaksi_id atau opening_rincian_id terisi. Unique generated target_key (`I:<id>`/`O:<id>`) bersama pembayaran_transaksi_id. Lock target invoice/rincian sebelum menjumlah alokasi. Pelanggan/pemasok, akun kontrol, unit, dan mata uang harus sama. Tidak membolehkan lebih bayar. Reversal memakai arah -1 seperti pembayaran_angsuran. Index target dan pembayaran.

## Tabel pendukung 3: mutasi_stok

| Field | Tipe / aturan |
|---|---|
| id | B PK |
| produk_id | B FK produk |
| transaksi_id | B? FK transaksi |
| transaksi_barang_id | B? FK transaksi_barang |
| opening_rincian_id | B? FK saldo_awal_rincian |
| tanggal | DATE |
| urutan | BIGINT; monotonic per produk memakai lock |
| jenis | S(30): opening/pembelian/penjualan/opname/reversal |
| kuantitas_masuk, kuantitas_keluar | Q >=0; tepat satu positif |
| nilai_masuk, nilai_keluar | N >=0; satu arah sesuai kuantitas |
| harga_pokok | C >=0 |
| saldo_kuantitas | Q >=0 |
| saldo_nilai | N >=0 |
| reversal_of_id | B? FK mutasi_stok UQ |
| source_key | S(100) UQ; deterministik per baris sumber |
| created_at | TS |

CHECK tepat satu transaksi_id/opening_rincian_id. UQ(produk_id,urutan), index(produk_id,tanggal,urutan). Jenis opening bersumber rincian saldo awal terkunci; mutasi operasional hanya muncul saat post. Baris tidak dihapus ketika reverse. Cache stok = SUM masuk-keluar termasuk pembalik. Nilai_pokok penjualan memakai nilai_keluar aktual sehingga pembulatan tidak membuat selisih ledger.

## Tabel pendukung 4: saldo_awal_rincian

| Field | Tipe / aturan |
|---|---|
| id | B PK |
| saldo_awal_akun_id | B FK saldo_awal_akun |
| jenis | S(30): stok/piutang_usaha/utang_usaha/pinjaman/aset/akumulasi_aset |
| kode_referensi | S(50); UQ(saldo_awal_akun_id,kode_referensi) |
| pihak_id | B? FK pihak |
| produk_id | B? FK produk |
| aset_tetap_id | B? FK aset_tetap |
| pinjaman_id | B? FK pinjaman |
| tanggal_asal, jatuh_tempo | DATE? |
| kuantitas | Q? |
| harga_pokok | C? |
| nominal | N >=0 |
| metadata | JSON?; informasi migrasi tervalidasi |
| created_at | TS |

Jenis menentukan field wajib: stok memakai produk+qty+cost; piutang/utang memakai pihak+invoice asal+jatuh tempo; pinjaman memakai pihak+kontrak/jadwal; aset memakai aset dan nilai perolehan; akumulasi_aset memakai aset dan akumulasi awal. Jumlah nominal tiap kelompok harus sama dengan saldo kontrol terkait. Metadata tidak menggantikan FK/harga/jurnal.

Relasi melingkar aset/pinjaman dengan opening_rincian ditambahkan migration tahap akhir setelah semua tabel ada. Dua pointer harus konsisten di service; bila ingin menghindari redundansi, field opening_rincian_id pada aset/pinjaman menjadi pointer satu-satunya dan referensi kebalikannya di-query, lalu catat keputusan sebelum migration. Blueprint default memakai kedua pointer dengan validator konsistensi.

## Aturan migration dan integritas

1. Buat users, unit, periode, akun, pihak, produk, saldo_awal_akun.
2. Buat transaksi (self-FK reversal), detail, barang, pinjaman, angsuran, aset, bukti, laporan_desa, log, opname/detail.
3. Buat empat tabel pendukung; tambahkan FK silang setelah semua tabel tersedia.
4. Perluas precision, tambahkan kolom teknis, CHECK, unique/index, dan constraint sumber.
5. Seed akun/mapping dalam satu transaksi; cek seluruh system_key yang dipakai service tersedia aktif.
6. Tes migration fresh hanya pada database test; perubahan setelah deployment memakai migration baru, tidak mengedit sejarah migration.

posting_key penyusutan: `DEP:<aset_id>:<periode_id>` hanya pada transaksi efektif yang belum reversed. Setelah reverse, kosongkan posting_key original dalam transaksi yang sama (pengecualian metadata, bukan nominal) agar repost terkoreksi dapat memakai key. Audit perubahan. Unique DB mencegah dua penyusutan efektif, row lock aset/periode mencegah race. Pola sama dapat digunakan untuk opname.

Request idempotency: request_key unik, request_hash payload canonical. Key sama+hash sama mengembalikan hasil terdahulu; key sama+payload berbeda menolak 409. Posting draft yang sama dua kali tidak menambah jurnal. Nomor transaksi dari ID/ULID atau counter terkunci; jangan `MAX(nomor)+1` tanpa lock.

## Jika kampus mewajibkan tepat 18 tabel

Jangan hapus tabel pendukung secara diam-diam. Ajukan pembaruan PDM atau batasi secara tertulis fitur pembayaran sebagian, alokasi invoice, dan histori migrasi. Paket ini memilih ketepatan riwayat operasional dengan 22 tabel bisnis; batas persis 18 tabel belum menjadi instruksi pengguna pada permintaan ini.

## Idempotency tindakan nontransaksi

Untuk submit/approve/close/reopen/import-lock dan transition lain, simpan request_key+request_hash dan result_ref di log_aktivitas dalam DB transaction yang sama dengan perubahan. Key yang sudah ada dan hash cocok mengembalikan referensi hasil; hash beda menolak409. Unique key pada log menjadi pengaman race. Source create transaksi memakai transaksi.request_key; command post/reverse memakai request_key transition tersendiri agar payload create dan post tidak bertabrakan. Retry setelah rollback tidak menemukan log sukses dan dapat dijalankan ulang.
