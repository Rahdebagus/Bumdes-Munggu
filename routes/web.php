<?php

use App\Livewire\Accounting\ManualJournal;
use App\Livewire\Dashboard\Index as Dashboard;
use App\Livewire\MasterData\Accounts;
use App\Livewire\MasterData\Parties;
use App\Livewire\MasterData\Products;
use App\Livewire\MasterData\Units;
use App\Livewire\OpeningBalance\Index as OpeningBalanceIndex;
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
});
