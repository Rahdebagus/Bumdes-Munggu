<?php

namespace App\Livewire\Trading;

use App\Domain\Accounting\PostDocument;
use App\Domain\MasterData\SaveProduct;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\ProdukKategori;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\Akun;
use App\Models\Pihak;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Form Penjualan / Pembelian. State form (baris barang, total, autocomplete)
 * dikelola Alpine di browser sehingga tambah/hapus baris & hitung total tidak
 * me-reload halaman. Komponen ini hanya API server: save() dan
 * createProduct() mengembalikan array & tidak me-render ulang (skipRender),
 * jadi posisi scroll dan fokus kursor tetap.
 */
#[Layout('components.layouts.app')]
class Create extends Component
{
    use AuthorizesRequests;

    public string $jenis = 'penjualan'; // penjualan | pembelian

    /** Draft yang sedang diedit (?draft=ID). */
    #[Url(as: 'draft')]
    public ?int $draftId = null;

    public function mount(string $jenis = 'penjualan'): void
    {
        abort_unless(in_array($jenis, ['penjualan', 'pembelian'], true), 404);
        $this->jenis = $jenis;

        if ($this->draftId) {
            $draft = Transaksi::findOrFail($this->draftId);
            abort_unless($draft->status === TransaksiStatus::Draft && $draft->jenis_transaksi->value === $jenis, 404);
        }
    }

    /**
     * Simpan dokumen (baru / edit draft) lalu, bila $post, langsung posting.
     *
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public function save(array $form, bool $post = true, bool $updateHargaBeli = false): array
    {
        $this->skipRender();
        $this->authorize('perform-admin-mutation');

        $isKredit = ($form['metode_pembayaran'] ?? '') === 'kredit';
        $validator = Validator::make($form, [
            'tanggal' => ['required', 'date'],
            'unit_usaha_id' => ['required', 'integer', 'exists:unit_usaha,id'],
            'pihak_id' => [$isKredit ? 'required' : 'nullable', 'integer', 'exists:pihak,id'],
            'metode_pembayaran' => ['required', 'in:tunai,kredit'],
            'akun_kas_id' => [$isKredit ? 'nullable' : 'required', 'integer', Rule::exists('akun', 'id')->where('is_cash', true)],
            'jatuh_tempo' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'integer', 'exists:produk,id'],
            'items.*.kuantitas' => ['required', 'numeric', 'gt:0'],
            'items.*.harga' => ['required', 'numeric', 'min:0'],
            'items.*.diskon' => ['nullable', 'numeric', 'min:0'],
            'items.*.pajak' => ['nullable', 'numeric', 'min:0'],
        ], [
            'pihak_id.required' => $this->jenis === 'penjualan' ? 'Penjualan kredit wajib memilih pelanggan.' : 'Pembelian kredit wajib memilih pemasok.',
            'akun_kas_id.required' => 'Metode tunai wajib memilih akun kas/bank.',
            'items.*.produk_id.required' => 'Pilih produk.',
            'items.*.kuantitas.gt' => 'Qty harus lebih dari 0.',
        ]);

        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray(), 'message' => 'Periksa kembali isian yang ditandai merah.'];
        }

        $data = $validator->validated();
        foreach ($data['items'] as $idx => $it) {
            $net = Decimal::sub(Decimal::mul($it['kuantitas'], $it['harga']), $it['diskon'] ?? 0);
            if (Decimal::compare($net, 0) < 0) {
                return ['ok' => false, 'errors' => ["items.$idx.diskon" => ['Diskon melebihi qty × harga.']], 'message' => 'Diskon melebihi qty × harga.'];
            }
        }

        $data['akun_kas_id'] = $isKredit ? null : $data['akun_kas_id'];
        $data['jatuh_tempo'] = $isKredit ? ($data['jatuh_tempo'] ?? $this->defaultJatuhTempo($data['tanggal'])) : null;
        $data['items'] = array_values(array_map(fn ($it) => [
            'produk_id' => (int) $it['produk_id'],
            'kuantitas' => (string) $it['kuantitas'],
            'harga' => (string) $it['harga'],
            'diskon' => (string) ($it['diskon'] ?? '0'),
            'pajak' => (string) ($it['pajak'] ?? '0'),
        ], $data['items']));

        $existing = $this->draftId ? Transaksi::find($this->draftId) : null;

        try {
            $trx = app(SaveTradingDraft::class)->handle($data, TransaksiJenis::from($this->jenis), auth()->user(), $existing);
        } catch (DomainException $e) {
            return ['ok' => false, 'errors' => [], 'message' => $e->getMessage()];
        }

        if ($updateHargaBeli && $this->jenis === 'pembelian') {
            foreach ($data['items'] as $it) {
                Produk::whereKey($it['produk_id'])->update(['harga_beli' => $it['harga']]);
            }
        }

        $warning = null;
        $posted = false;
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
            'stok' => Produk::whereIn('id', array_column($data['items'], 'produk_id'))->pluck('stok_terkini', 'id')
                ->map(fn ($v) => (float) $v)->all(),
            'hargaBeli' => $updateHargaBeli ? array_column($data['items'], 'harga', 'produk_id') : [],
        ];
    }

    /**
     * Tambah produk baru dari modal autocomplete; langsung dipakai di baris.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createProduct(array $data): array
    {
        $this->skipRender();
        $this->authorize('perform-admin-mutation');

        $validator = Validator::make($data, [
            'nama_produk' => ['required', 'string', 'max:100', Rule::unique('produk', 'nama_produk')],
            'satuan' => ['required', 'string', 'max:20'],
            'kategori' => ['required', ProdukKategori::rule()],
            'harga_beli' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
        ], [
            'nama_produk.required' => 'Nama produk wajib diisi.',
            'nama_produk.unique' => 'Nama produk sudah ada.',
            'satuan.required' => 'Satuan wajib diisi.',
            'kategori.required' => 'Pilih kategori.',
            'harga_beli.required' => 'Harga beli wajib diisi.',
            'harga_jual.required' => 'Harga jual wajib diisi.',
        ]);

        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray()];
        }

        $produk = app(SaveProduct::class)->handle($validator->validated() + ['status' => 'aktif']);

        return ['ok' => true, 'produk' => $this->productRow($produk->refresh()->load('unitUsaha'))];
    }

    /** Jatuh tempo default: penjualan +30 hari; pembelian = tanggal bayar borongan berikutnya (10/20/akhir bulan). */
    private function defaultJatuhTempo(string $tanggal): string
    {
        $t = Carbon::parse($tanggal);
        if ($this->jenis === 'penjualan') {
            return $t->copy()->addDays(30)->toDateString();
        }

        foreach ([10, 20] as $day) {
            if ($t->day < $day) {
                return $t->copy()->day($day)->toDateString();
            }
        }

        return $t->day < $t->copy()->endOfMonth()->day
            ? $t->copy()->endOfMonth()->toDateString()
            : $t->copy()->addMonthNoOverflow()->day(10)->toDateString();
    }

    /** @return array<string, mixed> */
    private function productRow(Produk $p): array
    {
        return [
            'id' => $p->id,
            'kode' => $p->kode_produk,
            'nama' => $p->nama_produk,
            'satuan' => $p->satuan,
            'kategori' => $p->kategori?->label() ?? '',
            'unit_id' => $p->unit_usaha_id,
            'harga_beli' => (float) $p->harga_beli,
            'harga_jual' => (float) $p->harga_jual,
            'stok' => (float) $p->stok_terkini,
            'lacak_stok' => (bool) $p->lacak_stok,
        ];
    }

    /** @return array<string, mixed>|null */
    private function draftPayload(): ?array
    {
        if (! $this->draftId) {
            return null;
        }

        $trx = Transaksi::with('barang')->findOrFail($this->draftId);

        return [
            'id' => $trx->id,
            'nomor' => $trx->nomor_transaksi,
            'header' => [
                'tanggal' => $trx->tanggal->toDateString(),
                'unit_usaha_id' => $trx->unit_usaha_id,
                'pihak_id' => $trx->pihak_id,
                'metode_pembayaran' => $trx->metode_pembayaran ?? 'tunai',
                'akun_kas_id' => $trx->akun_kas_id,
                'jatuh_tempo' => $trx->jatuh_tempo?->toDateString(),
                'keterangan' => $trx->keterangan,
            ],
            'items' => $trx->barang->sortBy('nomor_baris')->map(fn ($b) => [
                'produk_id' => $b->produk_id,
                'kuantitas' => (string) (float) $b->kuantitas,
                'harga' => (string) (float) $b->harga,
                'diskon' => (string) (float) $b->diskon,
                'pajak' => (string) (float) $b->pajak,
            ])->values()->all(),
        ];
    }

    public function render()
    {
        $akunKas = Akun::where('is_cash', true)->where('is_active', true)->orderBy('kode_akun')->get();
        $units = UnitUsaha::where('status', 'aktif')->orderBy('nama_unit')->get();

        return view('livewire.trading.create', [
            'title' => ($this->draftId ? 'Edit Draft ' : 'Transaksi ').ucfirst($this->jenis),
            'config' => [
                'jenis' => $this->jenis,
                'storageKey' => 'sia-bumdes:form:'.$this->jenis.($this->draftId ? ':draft-'.$this->draftId : ''),
                'today' => now()->toDateString(),
                'produk' => Produk::with('unitUsaha')->where('status', 'aktif')->orderBy('nama_produk')->get()
                    ->map(fn ($p) => $this->productRow($p))->values()->all(),
                'pihak' => Pihak::where('status', 'aktif')
                    ->where('jenis_pihak', $this->jenis === 'penjualan' ? 'pelanggan' : 'pemasok')
                    ->orderBy('nama')->get(['id', 'nama'])->all(),
                'units' => $units->map(fn ($u) => ['id' => $u->id, 'nama' => $u->nama_unit])->all(),
                'akunKas' => $akunKas->map(fn ($a) => ['id' => $a->id, 'label' => $a->kode_akun.' — '.$a->nama_akun, 'key' => $a->system_key])->all(),
                'defaultKasId' => $akunKas->firstWhere('system_key', 'cash')?->id ?? $akunKas->first()?->id,
                'kategori' => array_map(fn ($k) => ['value' => $k->value, 'label' => $k->label()], ProdukKategori::cases()),
                'draft' => $this->draftPayload(),
                'canMutate' => auth()->user()?->can('perform-admin-mutation') ?? false,
            ],
        ])->title(($this->draftId ? 'Edit Draft ' : '').ucfirst($this->jenis));
    }
}
