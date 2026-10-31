<?php

namespace App\Domain\Cash;

use App\Models\Akun;
use Illuminate\Support\Collection;

/**
 * Kategori kas dari config accounting.categories: akun lawan yang diizinkan,
 * akun lawan default (otomatis terpilih), dan akun kas/bank default. Dipakai
 * form Pemasukan & Pengeluaran dan validasi server.
 */
class CashCategories
{
    /** @return array<string, array<string, mixed>> kategori yang tampil di form (tanpa sistem & legacy) */
    public function visible(): array
    {
        return array_filter(config('accounting.categories', []), fn ($k) => ! in_array($k['grup'] ?? 'utama', ['sistem', 'legacy'], true));
    }

    /** @return array<string, mixed>|null */
    public function get(string $kode): ?array
    {
        return config("accounting.categories.{$kode}");
    }

    /** Akun lawan yang diizinkan untuk kategori. @return Collection<int, Akun> */
    public function allowedLawan(string $kode): Collection
    {
        $k = $this->get($kode);
        if (! $k || isset($k['redirect'])) {
            return collect();
        }

        $kodeList = array_filter(array_merge($k['lawan_kode'] ?? [], [$k['default_lawan'] ?? null]));

        return Akun::query()
            ->where('is_active', true)
            ->where(function ($q) use ($k, $kodeList) {
                $q->whereRaw('1 = 0');
                if (! empty($k['lawan'])) {
                    $q->orWhereIn('system_key', $k['lawan']);
                }
                if ($kodeList !== []) {
                    $q->orWhereIn('kode_akun', $kodeList);
                }
                if (! empty($k['lawan_jenis'])) {
                    $q->orWhere(fn ($w) => $w->whereIn('jenis_akun', $k['lawan_jenis'])->where('is_cash', false));
                }
                if (! empty($k['lawan_kas'])) {
                    $q->orWhere('is_cash', true);
                }
            })
            ->orderBy('kode_akun')
            ->get();
    }

    public function defaultLawanId(string $kode): ?int
    {
        $default = $this->get($kode)['default_lawan'] ?? null;
        if ($default) {
            return Akun::where('kode_akun', $default)->where('is_active', true)->value('id');
        }

        $allowed = $this->allowedLawan($kode);

        return $allowed->count() === 1 ? $allowed->first()->id : null;
    }

    public function defaultKasId(string $kode): ?int
    {
        $key = $this->get($kode)['kas'] ?? null;

        return $key ? Akun::where('system_key', $key)->where('is_active', true)->value('id') : null;
    }
}
