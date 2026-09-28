@props(['status'])

@php
    [$classes, $dot] = match ($status) {
        \App\Enums\DocumentStatus::Draft => [
            'border-slate-200 bg-slate-100 text-slate-700',
            'bg-slate-500',
        ],

        \App\Enums\DocumentStatus::Submitted => [
            'border-blue-200 bg-blue-50 text-blue-700',
            'bg-blue-500',
        ],

        \App\Enums\DocumentStatus::WaitingApproval => [
            'border-amber-200 bg-amber-50 text-amber-700',
            'bg-amber-500',
        ],

        \App\Enums\DocumentStatus::Approved => [
            'border-emerald-200 bg-emerald-50 text-emerald-700',
            'bg-emerald-500',
        ],

        \App\Enums\DocumentStatus::Rejected => [
            'border-red-200 bg-red-50 text-red-700',
            'bg-red-500',
        ],

        \App\Enums\DocumentStatus::WaitingSignature => [
            'border-violet-200 bg-violet-50 text-violet-700',
            'bg-violet-500',
        ],

        \App\Enums\DocumentStatus::Signing => [
            'border-indigo-200 bg-indigo-50 text-indigo-700',
            'bg-indigo-500',
        ],

        \App\Enums\DocumentStatus::Signed => [
            'border-emerald-200 bg-emerald-50 text-emerald-700',
            'bg-emerald-500',
        ],

        \App\Enums\DocumentStatus::SignFailed => [
            'border-red-200 bg-red-50 text-red-700',
            'bg-red-500',
        ],
    };
@endphp

<span
    {{ $attributes->merge([
        'class' => "inline-flex items-center gap-2 whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm {$classes}",
    ]) }}
>
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>

    {{ $status->label() }}
</span>
