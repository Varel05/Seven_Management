<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center gap-1.5 px-4 py-2 bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-xs hover:shadow-md hover:shadow-rose-600/25 active:scale-95 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 transition-all duration-150 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
