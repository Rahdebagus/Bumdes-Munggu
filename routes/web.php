<?php

use App\Http\Controllers\EvidenceDownloadController;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Accounting\ManualJournal;
use App\Livewire\Accounting\Transactions\Index as TransactionsIndex;
use App\Livewire\Accounting\Transactions\Show as TransactionsShow;
use App\Livewire\Cash\Create as CashCreate;
use App\Livewire\Dashboard\Index as Dashboard;
use App\Livewire\FixedAssets\Create as AssetCreate;
use App\Livewire\FixedAssets\Index as AssetsIndex;
use App\Livewire\FixedAssets\Show as AssetShow;
use App\Livewire\Inventory\Stock as StockIndex;
use App\Livewire\Inventory\Stocktakes\Create as StocktakeCreate;
use App\Livewire\Inventory\Stocktakes\Index as StocktakesIndex;
use App\Livewire\Inventory\Stocktakes\Show as StocktakeShow;
use App\Livewire\Lending\Loans\Create as LoanCreate;
use App\Livewire\Lending\Loans\Index as LoansIndex;
use App\Livewire\Lending\Loans\Show as LoanShow;
use App\Livewire\MasterData\Accounts;
use App\Livewire\MasterData\Parties;
use App\Livewire\MasterData\Products;
use App\Livewire\MasterData\Units;
use App\Livewire\OpeningBalance\Index as OpeningBalanceIndex;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Trading\Create as TradingCreate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Login/logout disediakan Fortify. Semua halaman aplikasi wajib auth + user aktif.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Master data (mutasi dibatasi Admin lewat policy di komponen).
    Route::get('/master/unit-usaha', Units::class)->name('master.units');
    Route::get('/master/akun', Accounts::class)->name('master.accounts');
    Route::get('/master/pihak', Parties::class)->name('master.parties');
    Route::get('/master/produk', Products::class)->name('master.products');

    // Saldo awal (opening balance).
    Route::get('/saldo-awal', OpeningBalanceIndex::class)->name('opening-balance');

    // Akuntansi: jurnal penyesuaian (post/reverse via PostingService).
    Route::get('/jurnal-penyesuaian', ManualJournal::class)->name('accounting.manual-journal');

    // Dagang (buat draft → detail → posting).
    Route::get('/penjualan/baru', TradingCreate::class)->defaults('jenis', 'penjualan')->name('sales.create');
    Route::get('/pembelian/baru', TradingCreate::class)->defaults('jenis', 'pembelian')->name('purchases.create');

    // Kas (pemasukan/pengeluaran/pelunasan).
    Route::get('/kas/baru', CashCreate::class)->name('cash.create');

    // Daftar & detail transaksi (drilldown jurnal + kartu stok, post/reverse).
    Route::get('/transaksi', TransactionsIndex::class)->name('transactions.index');
    Route::get('/transaksi/{transaksi}', TransactionsShow::class)->name('transactions.show');

    // Usaha kredit: pinjaman, jadwal, pembayaran angsuran.
    Route::get('/pinjaman', LoansIndex::class)->name('loans.index');
    Route::get('/pinjaman/baru', LoanCreate::class)->name('loans.create');
    Route::get('/pinjaman/{pinjaman}', LoanShow::class)->name('loans.show');

    // Persediaan + kartu stok.
    Route::get('/persediaan', StockIndex::class)->name('inventory.stock');

    // Stok opname (workflow + approval Pimpinan).
    Route::get('/stok-opname', StocktakesIndex::class)->name('stocktakes.index');
    Route::get('/stok-opname/baru', StocktakeCreate::class)->name('stocktakes.create');
    Route::get('/stok-opname/{opname}', StocktakeShow::class)->name('stocktakes.show');

    // Aset tetap + penyusutan.
    Route::get('/aset', AssetsIndex::class)->name('assets.index');
    Route::get('/aset/baru', AssetCreate::class)->name('assets.create');
    Route::get('/aset/{aset}', AssetShow::class)->name('assets.show');

    // Laporan, rekonsiliasi, dan ekspor (PDF/XLSX dari dataset yang sama).
    Route::get('/laporan', ReportsIndex::class)->name('reports.index');
    Route::get('/laporan/ekspor', ReportExportController::class)->name('reports.export');

    // Unduh bukti transaksi (disk private, attachment).
    Route::get('/bukti/{bukti}/download', EvidenceDownloadController::class)->name('evidence.download');
});
