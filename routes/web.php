<?php

use App\Livewire\Dashboard\Index as Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Login/logout disediakan Fortify. Semua halaman aplikasi wajib auth + user aktif.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
});
