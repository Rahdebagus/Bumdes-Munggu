<?php

namespace App\Livewire\Cash;

use App\Domain\Accounting\PostDocument;
use App\Domain\Cash\CashCategories;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\Cash\SettlementQuery;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiStatus;
use App\Models\Akun;
use App\Models\AlokasiPelunasan;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pemasukan & Pengeluaran kas di luar jual-beli barang. Seperti form dagang,
 * state dikelola Alpine (tanpa reload); komponen ini API server yang
 * mengembalikan array dan tidak me-render ulang.
 */
#[Layout('components.layouts.app')]
#[Title('Pemasukan & Pengeluaran')]
class Create extends Component
{
    use AuthorizesRequests;

    private const PELUNASAN = ['pelunasan_piutang', 'pelunasan_utang'];

    #[Url(as: 'draft')]
    public ?int $draftId = null;

    /** Kategori awal (?kategori=pelunasan_utang). */
    #[Url(as: 'kategori')]
    public ?string $kategoriAwal = null;

    public function mount(): void
    {
        if ($this->draftId) {
            $draft = Transaksi::findOrFail($this->draftId);
            abort_unless($draft->status === TransaksiStatus::Draft
                && in_array($draft->jenis_transaksi->value, ['pemasukan', 'pengeluaran'], true), 404);
        }
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public function save(array $form, bool $post = true): array
    {
        $this->skipRender();
        $this->authorize('perform-admin-mutation');

        $cats = app(CashCategories::class);
        $kode = (string) ($form['kategori_kode'] ?? '');
        $kategori = $cats->get($kode);
        if (! $kategori || in_array($kategori['grup'] ?? '', ['sistem', 'legacy'], true)) {
            return ['ok' => false, 'errors' => ['kategori_kode' => ['Pilih kategori.']], 'message' => 'Pilih kategori.'];
        }
        if (isset($kategori['redirect'])) {
            return ['ok' => false, 'errors' => ['kategori_kode' => [$kategori['redirect']['pesan']]], 'message' => $kategori['redirect']['pesan']];
        }

        $isPelunasan = in_array($kode, self::PELUNASAN, true);
        $rules = [
            'tanggal' => ['required', 'date'],
            'kategori_kode' => ['required', 'string'],
            'unit_usaha_id' => ['nullable', 'integer', 'exists:unit_usaha,id'],
            'akun_kas_id' => ['required', 'integer', Rule::exists('akun', 'id')->where('is_cash', true)->where('is_active', true)],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
        if ($isPelunasan) {
            $rules['allocations'] = ['required', 'array', 'min:1'];
            $rules['allocations.*.invoice_transaksi_id'] = ['required', 'integer', 'distinct', 'exists:transaksi,id'];
            $rules['allocations.*.nominal'] = ['required', 'numeric', 'gt:0'];
        } else {
            $allowed = $cats->allowedLawan($kode)->pluck('id')->all();
            $rules['akun_lawan_id'] = ['required', 'integer', Rule::in($allowed), 'different:akun_kas_id'];
            $rules['nominal'] = ['required', 'numeric', 'gt:0'];
        }

        $validator = Validator::make($form, $rules, [
            'akun_lawan_id.in' => 'Akun lawan tidak sesuai kategori.',
            'akun_lawan_id.different' => 'Akun lawan tidak boleh sama dengan akun kas.',
            'allocations.*.invoice_transaksi_id.distinct' => 'Nota yang sama dipilih dua kali.',
        ]);
        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray(), 'message' => 'Periksa kembali isian yang ditandai merah.'];
        }
        $data = $validator->validated();

        $payload = [
            'tanggal' => $data['tanggal'],
            'kategori_kode' => $kode,
            'unit_usaha_id' => $data['unit_usaha_id'] ?? null,
            'akun_kas_id' => (int) $data['akun_kas_id'],
            'keterangan' => $data['keterangan'] ?? null,
        ];
        if ($isPelunasan) {
            $payload['allocations'] = array_map(fn ($a) => [
                'invoice_transaksi_id' => (int) $a['invoice_transaksi_id'],
                'nominal' => (string) $a['nominal'],
            ], $data['allocations']);
            // Pihak dokumen = pihak nota (bila semua nota dari pihak yang sama).
            $pihak = Transaksi::whereIn('id', array_column($payload['allocations'], 'invoice_transaksi_id'))->pluck('pihak_id')->unique();
            $payload['pihak_id'] = $pihak->count() === 1 ? $pihak->first() : null;
        } else {
            $payload['akun_lawan_id'] = (int) $data['akun_lawan_id'];
            $payload['nominal'] = (string) $data['nominal'];
        }

        $existing = $this->draftId ? Transaksi::find($this->draftId) : null;

        try {
            $trx = app(SaveCashDraft::class)->handle($payload, auth()->user(), $existing);
        } catch (DomainException $e) {
            return ['ok' => false, 'errors' => [], 'message' => $e->getMessage()];
        }

        $posted = false;
        $warning = null;
        if ($post) {
            try {
                $trx = app(PostDocument::class)->handle($trx, auth()->user());
                $posted = true;
            } catch (DomainException $e) {
                $warning = 'Tersimpan sebagai DRAFT, belum diposting: '.$e->getMessage();
            }
        }

        return [
            'ok' => true,
            'id' => $trx->id,
            'nomor' => $trx->nomor_transaksi,
            'posted' => $posted,
            'warning' => $warning,
            'showUrl' => route('transactions.show', $trx),
            'notaUrl' => route('transactions.nota', $trx),
            'outstanding' => $this->outstanding(),
        ];
    }

    /**
     * Nota kredit yang masih punya sisa (untuk pelunasan).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function outstanding(): array
    {
        $settlement = app(SettlementQuery::class);
        $out = [];
        foreach (['pelunasan_piutang' => 'penjualan', 'pelunasan_utang' => 'pembelian'] as $kode => $jenis) {
            $invoices = Transaksi::with('pihak')
                ->where('jenis_transaksi', $jenis)->where('metode_pembayaran', 'kredit')
                ->whereNotNull('posted_at')->where('status', 'posted')
                ->orderBy('tanggal')->get();
            $terbayar = $settlement->invoiceAllocatedMany($invoices->pluck('id')->all(), excludeCashTrxId: $this->draftId);

            $out[$kode] = $invoices
                ->map(fn ($t) => ['trx' => $t, 'sisa' => Decimal::sub((string) $t->total, $terbayar[$t->id] ?? '0')])
                ->filter(fn ($r) => Decimal::isPositive($r['sisa']))
                ->map(fn ($r) => [
                    'id' => $r['trx']->id,
                    'label' => $r['trx']->nomor_transaksi.' · '.($r['trx']->pihak?->nama ?? 'tanpa pihak').' · '.$r['trx']->tanggal->format('d/m/Y'),
                    'sisa' => (float) $r['sisa'],
                ])->values()->all();
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function draftPayload(): ?array
    {
        if (! $this->draftId) {
            return null;
        }

        $trx = Transaksi::findOrFail($this->draftId);

        return [
            'id' => $trx->id,
            'header' => [
                'tanggal' => $trx->tanggal->toDateString(),
                'kategori_kode' => $trx->kategori_kode,
                'unit_usaha_id' => $trx->unit_usaha_id,
                'akun_kas_id' => $trx->akun_kas_id,
                'akun_lawan_id' => $trx->akun_lawan_id,
                'nominal' => (string) (float) $trx->total,
                'keterangan' => $trx->keterangan,
            ],
            'allocations' => AlokasiPelunasan::where('pembayaran_transaksi_id', $trx->id)->get()
                ->map(fn ($a) => ['invoice_transaksi_id' => $a->invoice_transaksi_id, 'nominal' => (string) (float) $a->nominal])->all(),
        ];
    }

    public function render()
    {
        $cats = app(CashCategories::class);
        $categories = [];
        foreach ($cats->visible() as $kode => $k) {
            $categories[$kode] = [
                'label' => $k['label'],
                'arah' => $k['arah'],
                'grup' => $k['grup'] ?? 'utama',
                'redirect' => isset($k['redirect']) ? $k['redirect'] + ['url' => route($k['redirect']['route'])] : null,
                'lawanOptions' => $cats->allowedLawan($kode)->map(fn ($a) => ['id' => $a->id, 'label' => $a->kode_akun.' — '.$a->nama_akun])->values()->all(),
                'defaultLawanId' => $cats->defaultLawanId($kode),
                'defaultKasId' => $cats->defaultKasId($kode),
            ];
        }

        $akunKas = Akun::where('is_cash', true)->where('is_active', true)->orderBy('kode_akun')->get();
        $kategoriAwal = $this->kategoriAwal && isset($categories[$this->kategoriAwal]) ? $this->kategoriAwal : 'biaya_pegawai';

        return view('livewire.cash.create', [
            'config' => [
                'storageKey' => 'sia-bumdes:form:kas'.($this->draftId ? ':draft-'.$this->draftId : ''),
                'today' => now()->toDateString(),
                'categories' => $categories,
                'defaultKategori' => $kategoriAwal,
                'units' => UnitUsaha::where('status', 'aktif')->orderBy('nama_unit')->get()->map(fn ($u) => ['id' => $u->id, 'nama' => $u->nama_unit])->all(),
                'akunKas' => $akunKas->map(fn ($a) => ['id' => $a->id, 'label' => $a->kode_akun.' — '.$a->nama_akun])->all(),
                'defaultKasId' => $akunKas->firstWhere('system_key', 'cash')?->id,
                'outstanding' => $this->outstanding(),
                'draft' => $this->draftPayload(),
                'urls' => ['sales' => route('sales.create'), 'purchases' => route('purchases.create')],
                'canMutate' => auth()->user()?->can('perform-admin-mutation') ?? false,
            ],
        ]);
    }
}
