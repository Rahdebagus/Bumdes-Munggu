# Data cutover BUMDes Munggu — Neraca 30 Juni 2026

Sumber: `06 LAP KEUANGAN JUNI 2026.xlsx` (laporan keuangan BUMDes "Tri Guna Sejahtera").
Dimuat oleh `ProduksiBumdesSeeder` sebagai saldo awal periode go-live **Juli 2026**.

| Berkas | Sheet Excel | Isi | Total |
|---|---|---|---:|
| `saldo_awal.csv` | NERACA | Kas, bank, aktiva, modal & dana, laba Jan–Jun | — |
| `persediaan.csv` | LAP PERSEDIAAN | Sisa stok per produk (lot ganda digabung) | 67.765.827,00 |
| `piutang_usaha.csv` | LAP PIUTANG | Nota piutang usaha, pelanggan = bidang | 752.016.100,00 |
| `pinjaman_pedagang.csv` | PIUTANG PINJAMAN | Sisa pinjaman per nasabah | 126.123.000,00 |
| `aset_tetap.csv` | INVENTARISS | Inventaris (4) & aktiva (3) beserta akumulasi | 95.064.000,00 / 86.138.200,00 |

Total aktiva = total pasiva = **Rp 1.215.728.153,78** (sama dengan neraca Excel).

Catatan pembersihan data:

- Nota 572 (Rp 5.000.000, upakara) tidak bertanggal di Excel → diberi tanggal nota sebelumnya
  dan ditandai di keterangan.
- Nota tanpa nomor diberi kode `TN-<baris>`; pinjaman tanpa kode diberi `PK-LAMA-<no>`;
  kode ganda (`PK012` vs `PK-012`) dibedakan menjadi `PK-012-2`.
- Nasabah "I Made Rai Suardika" tidak punya sisa pinjaman di daftar → tidak dimuat.
- Ejaan nama diperbaiki: "Merta gUnadi" → "Merta Gunadi", "Desa wisata" → "Desa Wisata".
