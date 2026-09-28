@props([
    'name',
    'title' => null,
])

{{--
    Modal reusable berbasis Alpine. Buka/tutup lewat window event:
      $dispatch('open-modal', '{{ $name }}')  /  $dispatch('close-modal', '{{ $name }}')
    Aksesibilitas: role=dialog, aria-modal, label, fokus awal, Escape close,
    dan pemulihan fokus ke elemen pemicu.
--}}
<div
    x-data="{
        show: false,
        trigger: null,
        openModal() { this.trigger = document.activeElement; this.show = true; this.$nextTick(() => this.$refs.panel?.focus()); },
        closeModal() { this.show = false; this.$nextTick(() => this.trigger?.focus()); },
    }"
    x-on:open-modal.window="$event.detail === '{{ $name }}' && openModal()"
    x-on:close-modal.window="$event.detail === '{{ $name }}' && closeModal()"
    x-on:keydown.escape.window="show && closeModal()"
    x-show="show" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog" aria-modal="true"
    @if($title) aria-label="{{ $title }}" @endif>

    <div class="absolute inset-0 bg-slate-900/50" x-show="show" x-transition.opacity @click="closeModal()"></div>

    <div x-ref="panel" tabindex="-1"
         x-show="show" x-transition
         class="relative w-full max-w-lg rounded-xl bg-white shadow-xl focus:outline-none">
        @if ($title)
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>
                <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100" @click="closeModal()" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif
        <div class="px-5 py-4">
            {{ $slot }}
        </div>
    </div>
</div>
