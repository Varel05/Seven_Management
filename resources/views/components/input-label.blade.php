@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-xs sm:text-sm text-slate-700 dark:text-slate-300']) }}>
    {{ $value ?? $slot }}
</label>
