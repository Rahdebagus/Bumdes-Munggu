# Modal awal BUMDes Munggu — cutover November 2026

Dipakai `ModalAwalBumdesSeeder` (dipanggil `DatabaseSeeder`).

| Berkas | Isi |
|---|---|
| `persediaan.csv` | Stok awal 90 produk per 1 November 2026: `kode_produk`, `nama_produk`, `unit`, `kuantitas`, `nilai` (Rp). Total **Rp 100.000.000**. |

Sumber: diekspor dari saldo awal terkunci database lokal (`bumdes_munggu`, periode 11/2026) pada 3 November 2026.
Produk dicocokkan ke master lewat `nama_produk`; `unit` harus sama dengan unit produk di master (`produk_bumdes.csv`).

Saldo awal yang terbentuk (semua lawan 3.1.01.00 Modal Desa Munggu, per unit):

| Akun | Unit | Nilai |
|---|---|---|
| 1.1.05.01 Persediaan | UNIT-DAGANG | 61.085.870 |
| 1.1.05.01 Persediaan | UNIT-ATK | 30.526.130 |
| 1.1.05.01 Persediaan | UNIT-KONSUMSI | 8.388.000 |
| 1.1.01.01 Kas Tunai | UNIT-KREDIT | 100.000.000 |
| **Total aktiva = Modal Desa** | | **200.000.000** |

Nilai kas, unit kas, dan periode diatur lewat konstanta di `DatabaseSeeder`. Kalau stok berubah, ubah `persediaan.csv` dan samakan `DatabaseSeeder::MODAL_BARANG`.
