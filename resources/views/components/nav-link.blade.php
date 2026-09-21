@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-white dark:border-emerald-400 text-sm font-semibold leading-5 text-white dark:text-white focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-emerald-100/80 dark:text-slate-400 hover:text-white dark:hover:text-slate-200 hover:border-white/50 dark:hover:border-slate-600 focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
