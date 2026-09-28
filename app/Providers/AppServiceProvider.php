<?php

namespace App\Providers;

use App\Domain\Accounting\JournalPlanRegistry;
use App\Domain\Accounting\Planners\ManualJournalPlanner;
use App\Enums\TransaksiJenis;
use App\Http\Middleware\EnsureUserActive;
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
        $this->app->singleton(JournalPlanRegistry::class, function () {
            $registry = new JournalPlanRegistry;
            $registry->register(TransaksiJenis::Penyesuaian->value, new ManualJournalPlanner);

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

        // Middleware status aktif dijaga persisten pada request update Livewire,
        // bukan hanya pada GET halaman awal.
        Livewire::addPersistentMiddleware([
            EnsureUserActive::class,
        ]);
    }
}
