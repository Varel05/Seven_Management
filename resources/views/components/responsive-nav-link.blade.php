@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-emerald-400 text-start text-base font-semibold text-white bg-emerald-900/80 dark:bg-emerald-950/40 dark:text-emerald-300 focus:outline-none transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-emerald-200/90 dark:text-slate-400 hover:text-white dark:hover:text-slate-200 hover:bg-emerald-900/50 dark:hover:bg-slate-800 hover:border-emerald-400/60 dark:hover:border-slate-600 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
