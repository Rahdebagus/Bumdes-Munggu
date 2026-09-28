<?php

namespace App\Domain\OpeningBalance;

use App\Models\LogAktivitas;
use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mengunci saldo awal periode cutover. Menolak (lock mismatch) bila belum ada
 * saldo, tidak seimbang, atau rincian != akun kontrol. Sukses → set
 * opening_locked_at/by pada periode dan locked_at/by pada tiap baris.
 */
class LockOpeningBalance
{
    public function __construct(private OpeningReconciliation $reconciliation) {}

    public function handle(PeriodeAkuntansi $periode, User $actor): void
    {
        DB::transaction(function () use ($periode, $actor) {
            $p = PeriodeAkuntansi::whereKey($periode->id)->lockForUpdate()->firstOrFail();

            if ($p->opening_locked_at !== null) {
                throw ValidationException::withMessages(['lock' => 'Saldo awal periode sudah dikunci.']);
            }

            if (SaldoAwalAkun::where('periode_id', $p->id)->count() === 0) {
                throw ValidationException::withMessages(['lock' => 'Belum ada saldo awal untuk dikunci.']);
            }

            $result = $this->reconciliation->check($p);

            if (! $result['balanced']) {
                throw ValidationException::withMessages([
                    'lock' => "Saldo awal tidak seimbang (debit {$result['total_debit']} ≠ kredit {$result['total_kredit']}).",
                ]);
            }

            if ($result['control_issues'] !== []) {
                $akun = implode(', ', array_map(fn ($i) => $i['akun'], $result['control_issues']));
                throw ValidationException::withMessages([
                    'lock' => "Rincian tidak sama dengan akun kontrol: {$akun}.",
                ]);
            }

            $now = now();
            $p->update(['opening_locked_at' => $now, 'opening_locked_by' => $actor->id]);
            SaldoAwalAkun::where('periode_id', $p->id)->update(['locked_at' => $now, 'locked_by' => $actor->id]);

            LogAktivitas::create([
                'user_id' => $actor->id,
                'modul' => 'OpeningBalance',
                'aksi' => 'lock',
                'referensi_tipe' => 'periode_akuntansi',
                'referensi_id' => $p->id,
                'alasan' => 'Kunci saldo awal cutover',
            ]);
        });
    }
}
