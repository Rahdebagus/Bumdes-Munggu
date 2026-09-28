<?php

namespace App\Livewire\OpeningBalance;

use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\OpeningImportParser;
use App\Domain\OpeningBalance\OpeningReconciliation;
use App\Domain\OpeningBalance\PreviewImport;
use App\Enums\SaldoRincianJenis;
use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\SaldoAwalRincian;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Saldo Awal')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    #[Url]
    public ?int $periodeId = null;

    public $file;

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    // Buat periode cutover minimal (manajemen periode penuh = tahap09).
    public int $newBulan = 1;

    public int $newTahun = 2026;

    // Form rincian.
    public ?int $rincianSaldoId = null;

    public string $rincianJenis = 'stok';

    public string $rincianKode = '';

    public string $rincianNominal = '0';

    public function createPeriode(): void
    {
        $this->authorize('perform-admin-mutation');
        $data = $this->validate([
            'newBulan' => ['required', 'integer', 'between:1,12'],
            'newTahun' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $mulai = CarbonImmutable::create($data['newTahun'], $data['newBulan'], 1);
        $periode = PeriodeAkuntansi::firstOrCreate(
            ['tahun' => $data['newTahun'], 'bulan' => $data['newBulan']],
            [
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $mulai->endOfMonth()->toDateString(),
                'status' => 'terbuka',
                'revision' => 0,
            ],
        );

        $this->periodeId = $periode->id;
        session()->flash('status', 'Periode cutover siap.');
    }

    public function preview(OpeningImportParser $parser, PreviewImport $previewImport): void
    {
        $this->authorize('perform-admin-mutation');
        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $rows = $parser->parse($this->file->getRealPath());
        $this->preview = $previewImport->handle($rows);
    }

    public function commit(CommitImport $commitImport): void
    {
        $this->authorize('perform-admin-mutation');
        $periode = $this->requirePeriode();

        if (! $this->preview) {
            throw ValidationException::withMessages(['import' => 'Unggah & preview dahulu.']);
        }

        $commitImport->handle($periode, $this->preview, auth()->user());

        $this->preview = null;
        $this->reset('file');
        session()->flash('status', 'Saldo awal tersimpan (draft, belum dikunci).');
    }

    public function addRincian(): void
    {
        $this->authorize('perform-admin-mutation');
        $periode = $this->requirePeriode();

        $validated = $this->validate([
            'rincianSaldoId' => [
                'required', 'integer',
                Rule::exists('saldo_awal_akun', 'id')->where('periode_id', $periode->id),
            ],
            'rincianJenis' => ['required', SaldoRincianJenis::rule()],
            'rincianKode' => ['required', 'string', 'max:50'],
            'rincianNominal' => ['required', 'numeric', 'min:0'],
        ]);

        if ($periode->opening_locked_at !== null) {
            throw ValidationException::withMessages(['rincianKode' => 'Saldo awal sudah dikunci.']);
        }

        SaldoAwalRincian::create([
            'saldo_awal_akun_id' => $validated['rincianSaldoId'],
            'jenis' => $validated['rincianJenis'],
            'kode_referensi' => $validated['rincianKode'],
            'nominal' => $validated['rincianNominal'],
        ]);

        $this->reset('rincianKode', 'rincianNominal');
        session()->flash('status', 'Rincian ditambahkan.');
    }

    public function lock(LockOpeningBalance $lockOpeningBalance): void
    {
        $this->authorize('perform-admin-mutation');
        $lockOpeningBalance->handle($this->requirePeriode(), auth()->user());
        session()->flash('status', 'Saldo awal dikunci.');
    }

    private function requirePeriode(): PeriodeAkuntansi
    {
        if (! $this->periodeId) {
            throw ValidationException::withMessages(['periodeId' => 'Pilih periode cutover dahulu.']);
        }

        return PeriodeAkuntansi::findOrFail($this->periodeId);
    }

    public function render()
    {
        $periode = $this->periodeId ? PeriodeAkuntansi::find($this->periodeId) : null;

        $openingRows = $periode
            ? SaldoAwalAkun::with(['akun', 'rincian'])->where('periode_id', $periode->id)->get()
            : collect();

        $recon = $periode ? app(OpeningReconciliation::class)->check($periode) : null;

        return view('livewire.opening-balance.index', [
            'periods' => PeriodeAkuntansi::orderByDesc('tahun')->orderByDesc('bulan')->get(),
            'periode' => $periode,
            'openingRows' => $openingRows,
            'controlRows' => $openingRows->filter(fn ($r) => in_array($r->akun?->system_key, [
                'inventory', 'trade_receivable', 'loan_receivable', 'trade_payable', 'fixed_asset', 'accumulated_depreciation',
            ], true)),
            'recon' => $recon,
            'jenisRincian' => SaldoRincianJenis::cases(),
        ]);
    }
}
