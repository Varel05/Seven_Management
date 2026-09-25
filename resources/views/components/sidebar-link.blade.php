@props(['active' => false, 'title' => ''])

@php
$baseClasses = $active
    ? 'bg-emerald-500 text-slate-950 dark:text-slate-950 shadow-md shadow-emerald-950/30 font-bold'
    : 'text-emerald-100/80 dark:text-slate-400 hover:text-white dark:hover:text-slate-100 hover:bg-emerald-800/40 dark:hover:bg-slate-800/60 font-medium';
@endphp

<a {{ $attributes->merge(['class' => "flex items-center rounded-xl text-xs transition-all duration-200 group {$baseClasses}", 'title' => $title]) }}
   :class="sidebarOpen ? 'gap-3 px-3.5 py-2.5 justify-start' : 'md:justify-center md:px-0 md:py-2.5 gap-3 px-3.5 py-2.5'">
    {{ $slot }}
</a>
