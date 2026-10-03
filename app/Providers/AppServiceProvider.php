<?php

namespace App\Providers;

use App\Domain\Accounting\JournalPlanRegistry;
use App\Domain\Accounting\Planners\ManualJournalPlanner;
use App\Domain\Cash\CashPostingStrategy;
use App\Domain\FixedAssets\DepreciationStrategy;
use App\Domain\Inventory\OpnameAdjustmentStrategy;
use App\Domain\Lending\InstallmentPaymentStrategy;
use App\Domain\Lending\LoanDisbursementStrategy;
use App\Domain\Trading\PurchasePostingStrategy;
use App\Domain\Trading\SalePostingStrategy;
use App\Enums\TransaksiJenis;
use App\Http\Middleware\EnsureUserActive;
use App\Models\BuktiTransaksi;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registry planner jurnal. Modul lain mendaftarkan planner-nya (tahap05+).
        $this->app->singleton(JournalPlanRegistry::class, function ($app) {
            $registry = new JournalPlanRegistry;
            $registry->register(TransaksiJenis::Penyesuaian->value, new ManualJournalPlanner);
            $registry->register(TransaksiJenis::Penjualan->value, $app->make(SalePostingStrategy::class));
            $registry->register(TransaksiJenis::Pembelian->value, $app->make(PurchasePostingStrategy::class));
            $registry->register(TransaksiJenis::Pemasukan->value, $app->make(CashPostingStrategy::class));
            $registry->register(TransaksiJenis::Pengeluaran->value, $app->make(CashPostingStrategy::class));
            $registry->register(TransaksiJenis::Pencairan->value, $app->make(LoanDisbursementStrategy::class));
            $registry->register(TransaksiJenis::Angsuran->value, $app->make(InstallmentPaymentStrategy::class));
            $registry->register(TransaksiJenis::Penyusutan->value, $app->make(DepreciationStrategy::class));
            $registry->registerSource('stok_opname', $app->make(OpnameAdjustmentStrategy::class));

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Kewenangan mutasi: hanya Admin yang boleh membuat/mengubah data.
        // Pimpinan bersifat baca + approval (matriks kewenangan dokumen01).
        Gate::define('perform-admin-mutation', fn (User $user) => $user->isAdmin());

        // Verifikasi bukti: Pimpinan boleh; Admin boleh asalkan bukan pengunggah
        // sendiri [TEKNIS] (matriks kewenangan dokumen01).
        Gate::define('verify-evidence', fn (User $user, BuktiTransaksi $bukti) => $user->isPimpinan()
            || ($user->isAdmin() && $bukti->uploaded_by !== $user->id));

        // Approval opname & laporan desa = Pimpinan (matriks kewenangan dokumen01).
        Gate::define('approve-stocktake', fn (User $user) => $user->isPimpinan());

        // Middleware status aktif dijaga persisten pada request update Livewire,
        // bukan hanya pada GET halaman awal.
        Livewire::addPersistentMiddleware([
            EnsureUserActive::class,
        ]);
    }
}
