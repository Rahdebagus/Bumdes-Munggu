<?php

namespace App\Livewire\Forms;

use App\Enums\AkunJenis;
use App\Enums\SaldoNormal;
use App\Models\Akun;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AccountForm extends Form
{
    public ?int $akunId = null;

    public string $kode_akun = '';

    public string $nama_akun = '';

    public string $jenis_akun = 'aset';

    public string $saldo_normal = 'debit';

    public ?string $system_key = null;

    public ?string $report_group = null;

    public bool $is_cash = false;

    public bool $is_active = true;

    public function rules(): array
    {
        return [
            'kode_akun' => ['required', 'string', 'max:20', Rule::unique('akun', 'kode_akun')->ignore($this->akunId)],
            'nama_akun' => ['required', 'string', 'max:100'],
            'jenis_akun' => ['required', AkunJenis::rule()],
            'saldo_normal' => ['required', SaldoNormal::rule()],
            'system_key' => ['nullable', 'string', 'max:50', Rule::unique('akun', 'system_key')->ignore($this->akunId)],
            'report_group' => ['nullable', 'string', 'max:50'],
            'is_cash' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function setAccount(Akun $akun): void
    {
        $this->akunId = $akun->id;
        $this->kode_akun = $akun->kode_akun;
        $this->nama_akun = $akun->nama_akun;
        $this->jenis_akun = $akun->jenis_akun->value;
        $this->saldo_normal = $akun->saldo_normal->value;
        $this->system_key = $akun->system_key;
        $this->report_group = $akun->report_group;
        $this->is_cash = (bool) $akun->is_cash;
        $this->is_active = (bool) $akun->is_active;
    }
}
