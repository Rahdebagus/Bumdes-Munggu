<?php

namespace App\Livewire\Forms;

use App\Enums\StatusUmum;
use App\Enums\UnitJenisUsaha;
use App\Models\UnitUsaha;
use Illuminate\Validation\Rule;
use Livewire\Form;

class UnitForm extends Form
{
    public ?int $unitId = null;

    public string $kode_unit = '';

    public string $nama_unit = '';

    public string $jenis_usaha = 'dagang';

    public string $penanggung_jawab = '';

    public string $status = 'aktif';

    public function rules(): array
    {
        return [
            'kode_unit' => ['required', 'string', 'max:20', Rule::unique('unit_usaha', 'kode_unit')->ignore($this->unitId)],
            'nama_unit' => ['required', 'string', 'max:100'],
            'jenis_usaha' => ['required', UnitJenisUsaha::rule()],
            'penanggung_jawab' => ['required', 'string', 'max:100'],
            'status' => ['required', StatusUmum::rule()],
        ];
    }

    public function setUnit(UnitUsaha $unit): void
    {
        $this->unitId = $unit->id;
        $this->kode_unit = $unit->kode_unit;
        $this->nama_unit = $unit->nama_unit;
        $this->jenis_usaha = $unit->jenis_usaha->value;
        $this->penanggung_jawab = $unit->penanggung_jawab;
        $this->status = $unit->status->value;
    }
}
