# 07 — Frontend Blade + Livewire

## Arah visual

Dashboard administrasi bersih dan profesional: latar slate terang, kartu putih, aksen emerald, tipografi sans-serif konsisten, angka tabular. Ini pilihan desain [TEKNIS], bukan warna wajib dari PDF. Dark mode opsional setelah fungsi inti selesai. UI berbahasa Indonesia, angka `Rp1.250.000,00`, tanggal `28 Sep 2026`. Nilai negatif diberi tanda minus dan label, bukan warna saja.

Frontend menggunakan komponen Blade buatan proyek dan Tailwind, dengan Livewire untuk state server. Gaya visual mengikuti kerapian shadcn/ui sebagai referensi desain saja. Buat sidebar, card, table, modal, alert-dialog, select, badge, tabs, drawer, skeleton dan toast sebagai komponen reusable. Tidak ada dependency shadcn/ui yang perlu dipasang. Grafik dapat memakai Chart.js melalui Vite; dataset tetap dari ReportService.

## Layout dan navigasi

Sidebar desktop 240–260 px, collapsed icon mode; mobile Sheet. Header memuat breadcrumb, judul halaman, periode/unit filter, profil pengguna. Konten responsif max-width yang nyaman tanpa memaksa seluruh tabel pada layar kecil. Filter global tersimpan di URL dan diteruskan ke drilldown/ekspor.

| Kelompok menu | Halaman |
|---|---|
| Dashboard | Ringkasan usaha |
| Transaksi | Penjualan, Pembelian, Pemasukan, Pengeluaran, Daftar transaksi |
| Usaha Kredit | Pinjaman, Jadwal Angsuran, Pembayaran Angsuran |
| Data Master | Unit Usaha, Akun, Pihak (tab pelanggan/pemasok/peminjam), Produk, Saldo Awal |
| Persediaan | Stok Barang, Kartu Stok, Stok Opname |
| Akuntansi | Jurnal, Buku Besar, Periode Akuntansi |
| Aset | Aset Tetap, Penyusutan |
| Laporan | Laba Rugi, Neraca, Perubahan Modal, Arus Kas, Kredit, Omzet Desa |
| Pengawasan | Audit/Rekonsiliasi, Bukti Transaksi, Histori Aktivitas |

Pimpinan melihat halaman baca dan antrean persetujuan. Tombol/route mutasi Admin dihilangkan dari UI; server tetap memeriksa policy.

## Dashboard

Baris pertama: nama BUMDes, periode, unit, badge periode terbuka/ditutup, waktu data diperbarui.

| Elemen | Definisi / interaksi |
|---|---|
| Saldo kas/bank | Saldo pada akhir rentang; klik buku besar kas/bank |
| Omzet dagang | Penjualan bersih rentang, bukan semua kas masuk |
| Pendapatan kredit | Bunga+denda/jasa terpetakan; pokok tidak masuk |
| Beban dan HPP | Tampilkan label terpisah agar tidak dihitung ganda |
| Laba bersih | Query sama dengan laba rugi |
| Persediaan | Nilai stok akhir; klik kartu stok |
| Piutang usaha / pinjaman | Dua kartu atau dua baris jelas |
| Utang usaha | Saldo outstanding akhir |
| Grafik tren | Pendapatan vs beban per bulan; unit dan periode konsisten |
| Grafik komposisi | Dagang/kredit/lain berdasarkan pendapatan |
| Peringatan | Stok minimum, angsuran terlambat, draft, bukti ditolak |
| Antrean Pimpinan | Opname dan laporan desa menunggu approval |

Setiap KPI menerima `{amount, comparison?, drilldownUrl}` dari backend. Persentase perubahan jika pembanding nol tampil `—`, jangan Infinity. Grafik memiliki tabel ringkas alternatif dan tidak memakai data random saat API kosong.

## Komponen reusable

- `PageHeader`, `PeriodUnitFilter`, `DataTable`, `Pagination`, `EmptyState`, `ErrorState`.
- `MoneyInput`: simpan decimal string canonical, pemisah lokal hanya display; jangan `parseFloat` untuk total resmi.
- `QuantityInput`, `PartyCombobox`, `ProductCombobox`, `AccountCombobox` dengan pencarian server.
- `StatusBadge` dengan mapping status terpusat.
- `EvidenceUploader`, `EvidenceList`, `JournalPreview`, `AuditTimeline`.
- `PostConfirmation`, `ReverseDialog` (alasan wajib), `ApprovalPanel`.
- `ReportToolbar` (periode/unit, cetak, PDF/XLSX, audit status).

Class halaman berada di app/Livewire/{Module}; view di resources/views/livewire/{module}; komponen presentasi di resources/views/components/ui dan shared. Livewire Form object mengelola input; Domain Action menangani bisnis. Hindari satu class dashboard yang memuat form semua modul. Search memakai wire:model.live.debounce.300ms, filter memakai URL query binding, pagination di server.

## Spesifikasi halaman penting

| Halaman | Kolom/field utama | Tindakan dan state |
|---|---|---|
| Penjualan/pembelian index | No, tanggal, pihak, unit, total, tunai/kredit, jatuh tempo, status | Search, filter, pagination, detail, tambah draft |
| Form dagang | Tanggal, unit, pihak, metode, akun kas atau jatuh tempo; baris produk qty/harga/diskon/pajak | Simpan draft, preview jurnal, post; harga pokok hanya server |
| Detail transaksi | Ringkasan, barang, jurnal, bukti, aktivitas, link reversal | Posted read-only; reverse melalui dialog |
| Pemasukan/pengeluaran | Kategori, akun kas, lawan, nominal, pihak, alokasi invoice | Saldo invoice sebelum/sesudah; pelunasan bukan pendapatan baru |
| Pinjaman | Peminjam, pokok, bunga, basis bunga, tenor, tanggal pertama | Preview seluruh jadwal; cairkan setelah draft valid |
| Angsuran | Tagihan pokok/bunga/denda, telah dibayar, sisa, nominal pembayaran | Bisa sebagian, pilih jadwal; receipt detail |
| Stok | Produk, jumlah, HPP rata-rata, nilai, minimum | Baca saja; link kartu stok |
| Kartu stok | Tanggal, sumber, masuk/keluar, qty/nilai saldo | Urutan posting; link sumber |
| Opname | Produk, snapshot stok, fisik, selisih, nilai, alasan | Draft → selesai → ajukan → approval → post |
| Saldo awal | Akun, unit, debit/kredit; tab rincian | Import preview errors per baris; lock jika cocok |
| Aset/penyusutan | Perolehan, residu, umur, mulai susut, akumulasi, nilai buku | Preview bulan, post; badge sudah diposting |
| Jurnal | Nomor, tanggal, akun, debit, kredit, source | Total footer, drilldown |
| Buku besar | Saldo awal, mutasi, saldo berjalan | Filter akun/periode/unit |
| Periode | Bulan/tahun, status, checklist rekonsiliasi | Pimpinan close/reopen dengan alasan |
| Laporan desa | Versi, angka snapshot, bukti, status, riwayat | Ajukan/approve/revisi/kirim/terima sesuai role |
| Audit | Nama check, expected, actual, selisih, PASS/WARNING/FAIL | Link sumber dan tindakan perbaikan |

## Form dan error

Gunakan Livewire Form objects untuk state/rules dan $this->validate(); mutation via wire:submit atau wire:click, error via @error. Validasi client membantu UX tetapi server final. Loading menonaktifkan tombol ganda, idempotency server tetap wajib. Unsaved changes guard untuk form panjang. Kesalahan per baris seperti `items.0.quantity` ditampilkan dekat input; error global menyebut tindakan pemulihan.

Dialog post merangkum tanggal, unit, total, jurnal seimbang, efek stok, dan bukti. AlertDialog reverse menjelaskan nomor asal dan tanggal pembalik serta meminta alasan. Periode ditutup memberi alasan spesifik. Tidak memakai toast sukses sebelum server commit.

## Responsif dan aksesibilitas

Periksa viewport 360, 768, 1280 px. Pada mobile kartu KPI 1–2 kolom; tabel dapat scroll horizontal dalam container tanpa memotong tombol. Input memiliki label; fokus keyboard terlihat; dialog mengembalikan fokus ke pemicu. Gunakan status teks+ikon, kontras memadai, dan ukuran tombol nyaman. Loading skeleton mempertahankan layout; empty state memberi tindakan sesuai role.

## Kriteria selesai

Seluruh menu punya halaman nyata, pagination dan filter bekerja pada server, tidak ada placeholder finansial, URL filter dapat dibagikan, role Pimpinan diuji, dan nilai dashboard/ekspor sama. Build dan tes Livewire lulus. Referensi: [Blade](https://laravel.com/docs/12.x/blade), [Livewire3](https://livewire.laravel.com/docs/3.x/installation).


## Pola implementasi Blade/Livewire

- `<x-ui.button>`, `<x-ui.input>`, `<x-ui.card>`, `<x-ui.badge>`, `<x-ui.modal>`, `<x-ui.table>` dan `<x-shared.journal-preview>` menerima props kecil dan attribute bag. Atribut wire:model/wire:click diteruskan ke elemen yang benar; jangan tertelan oleh wrapper.
- Gunakan WithPagination; resetPage() ketika search/filter berubah; berikan nama paginator berbeda jika ada dua tabel. `#[Url]` untuk filter yang dapat dibagikan dan allowlist sort di server.
- Form panjang menggunakan `wire:model` deferred atau `.blur`; `.live` hanya ketika benar-benar perlu. Kalkulasi HPP dan total resmi setelah validasi di server, bukan setiap ketikan dengan query mahal.
- Repeater baris barang memakai wire:key dari UUID baris stabil. Jangan memakai index array semata karena hapus/urut ulang dapat menggeser input.
- Dialog konfirmasi menampilkan preview server. `wire:loading.attr="disabled"` mencegah klik berulang di UI, namun idempotency server tetap wajib. Toast dapat muncul dari flash session atau event setelah commit.
- Upload memakai WithFileUploads untuk temporary upload, validasi MIME/size/ownership, lalu simpan melalui Evidence Action ke private disk. Jangan menampilkan temporary path atau membolehkan client memilih storage path.
- Untuk grafik Chart.js gunakan wire:ignore pada canvas, dataset aman dengan @js, event terarah untuk update data, dan destroy chart saat komponen dilepas. Jangan membuat instance ganda setelah filter/navigation. Tabel angka alternatif tetap tersedia.
- Gunakan Blade escaping `{{ }}` untuk konten pengguna. Hindari `{!! !!}` pada keterangan transaksi. Alpine hanya mengatur presentasi; nilai nominal/status/role yang dikirim browser tetap tidak dipercaya.
- `wire:navigate` opsional setelah navigasi normal benar. Uji hook init/cleanup chart, dialog dan event listener saat back/forward; jangan menjadikannya prasyarat fungsi inti.
- Modal harus mempunyai label aksesibel, fokus keyboard yang terkelola, Escape close bila aman, dan pemulihan fokus. Komponen buatan sendiri tetap harus diuji, bukan otomatis dianggap setara library aksesibilitas.
