<?php

namespace App\Domain\Shared;

use RuntimeException;

/**
 * Kesalahan domain dengan kode terstandar (dokumen09 §Penanganan error).
 * Livewire memetakan ini ke error bag; tes domain memeriksa $domainCode.
 */
class DomainException extends RuntimeException
{
    public function __construct(public readonly string $domainCode, string $message)
    {
        parent::__construct($message);
    }

    public static function staleVersion(string $message = 'Versi berubah; muat ulang lalu ulangi.'): self
    {
        return new self('STALE_VERSION', $message);
    }

    public static function idempotencyConflict(string $message = 'Request key sama dengan payload berbeda.'): self
    {
        return new self('IDEMPOTENCY_CONFLICT', $message);
    }

    public static function periodClosed(string $message = 'Periode tertutup; posting ditolak.'): self
    {
        return new self('PERIOD_CLOSED', $message);
    }

    public static function openingNotLocked(string $message = 'Saldo awal periode belum dikunci.'): self
    {
        return new self('OPENING_NOT_LOCKED', $message);
    }

    public static function unbalanced(string $message = 'Jurnal tidak seimbang (debit ≠ kredit).'): self
    {
        return new self('UNBALANCED', $message);
    }

    public static function invalidState(string $message): self
    {
        return new self('INVALID_STATE', $message);
    }

    public static function reverseDependency(string $message): self
    {
        return new self('REVERSE_DEPENDENCY', $message);
    }

    public static function insufficientStock(string $message): self
    {
        return new self('INSUFFICIENT_STOCK', $message);
    }

    public static function stockBackdate(string $message): self
    {
        return new self('STOCK_BACKDATE', $message);
    }

    public static function overpayment(string $message): self
    {
        return new self('OVERPAYMENT', $message);
    }
}
