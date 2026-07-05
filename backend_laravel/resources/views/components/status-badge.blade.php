@props([
    'label',
    'tone' => null,
])

@php
    $toneClasses = [
        'success' => 'inline-flex items-center rounded-full border border-success-200 bg-success-50 px-2 py-0.5 text-[10px] font-medium text-success-700 dark:border-success-500/20 dark:bg-success-500/10 dark:text-success-300',
        'warning' => 'inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300',
        'danger' => 'inline-flex items-center rounded-full border border-error-200 bg-error-50 px-2 py-0.5 text-[10px] font-medium text-error-700 dark:border-error-500/20 dark:bg-error-500/10 dark:text-error-300',
        'info' => 'inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-medium text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300',
        'neutral' => 'inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
        'verified' => 'inline-flex items-center rounded-full border border-brand-300 bg-brand-50 px-2 py-0.5 text-[10px] font-medium text-brand-600 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-300',
        'featured' => 'inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] font-medium text-violet-700 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-300',
        'promoted' => 'inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-[10px] font-medium text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-300',
    ];

    $normalized = str($label)->lower()->replace('_', ' ')->trim()->value();

    $resolvedTone = $tone;

    if ($resolvedTone === null) {
        $resolvedTone = match (true) {
            in_array($normalized, ['active', 'paid', 'accepted', 'completed', 'converted', 'open', 'public'], true) => 'success',
            in_array($normalized, ['inactive', 'expired', 'cancelled', 'overdue', 'rejected', 'closed', 'private'], true) => 'danger',
            in_array($normalized, ['expiring soon', 'due', 'partial', 'unpaid', 'frozen', 'trial'], true) => 'warning',
            in_array($normalized, ['pending', 'unverified'], true) => 'info',
            $normalized === 'verified' => 'verified',
            $normalized === 'featured' => 'featured',
            $normalized === 'promoted' => 'promoted',
            default => 'neutral',
        };
    }
@endphp

    <span {{ $attributes->merge(['class' => $toneClasses[$resolvedTone] ?? $toneClasses['neutral']]) }}>
        {{ $label }}
    </span>
