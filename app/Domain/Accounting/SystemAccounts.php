<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\DomainException;
use App\Models\Akun;

/**
 * Resolusi akun berdasarkan system_key (dokumen05). Memvalidasi akun ada &
 * aktif; menolak bila mapping belum lengkap agar posting tidak menyentuh akun
 * kontrol yang salah.
 */
class SystemAccounts
{
    /** @var array<string, int>|null */
    private ?array $cache = null;

    public function id(string $systemKey): int
    {
        $this->load();

        if (! isset($this->cache[$systemKey])) {
            throw DomainException::invalidState("Akun system_key '{$systemKey}' belum tersedia/aktif. Lengkapi chart of accounts.");
        }

        return $this->cache[$systemKey];
    }

    private function load(): void
    {
        if ($this->cache === null) {
            $this->cache = Akun::whereNotNull('system_key')
                ->where('is_active', true)
                ->pluck('id', 'system_key')
                ->all();
        }
    }
}
