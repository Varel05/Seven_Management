@php
    $logoFiles = [
        'images/logo.svg',
        'images/logo.png',
        'images/logo.webp',
        'images/logo.jpg',
        'images/logo.jpeg',
    ];
    $customLogo = null;
    foreach ($logoFiles as $file) {
        if (file_exists(public_path($file))) {
            $customLogo = asset($file);
            break;
        }
    }
@endphp

@if ($customLogo)
    <img src="{{ $customLogo }}" alt="Seven Accounting" {{ $attributes->merge(['class' => 'w-9 h-9 object-contain rounded-xl shadow-md group-hover:scale-105 transition-transform shrink-0']) }}>
@else
    <div {{ $attributes->merge(['class' => 'w-9 h-9 rounded-xl bg-emerald-500 text-slate-950 flex items-center justify-center font-black shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform shrink-0']) }}>
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
    </div>
@endif
