<?php

namespace App\Domain\MasterData;

use App\Models\Akun;
use App\Models\SaldoAwalAkun;
use App\Models\TransaksiDetail;
use Illuminate\Validation\ValidationException;

class SaveAccount
{
    /** Field yang mengubah makna historis akun (dokumen06). */
    private const LOCKED_WHEN_USED = ['kode_akun', 'system_key', 'jenis_akun', 'saldo_normal'];

    /** @param array<string, mixed> $data */
    public function handle(array $data, ?Akun $akun = null): Akun
    {
        if (! $akun) {
            return Akun::create($data);
        }

        if ($this->isUsed($akun)) {
            foreach (self::LOCKED_WHEN_USED as $field) {
                $lama = $akun->getRawOriginal($field);
                $baru = $data[$field] ?? null;
                if (array_key_exists($field, $data) && (string) $baru !== (string) $lama) {
                    throw ValidationException::withMessages([
                        "form.{$field}" => "Akun sudah dipakai; {$field} tidak boleh diubah (mengubah makna historis).",
                    ]);
                }
            }
        }

        $akun->update($data);

        return $akun;
    }

    private function isUsed(Akun $akun): bool
    {
        return TransaksiDetail::where('akun_id', $akun->id)->exists()
            || SaldoAwalAkun::where('akun_id', $akun->id)->exists();
    }
}
