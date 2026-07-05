<div class="panel-card p-5">
    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $label }}</p>
    <p class="panel-stat-value mt-4">{{ $value }}</p>
    @isset($hint)
        <p class="mt-2 text-sm text-slate-400">{{ $hint }}</p>
    @endisset
</div>
