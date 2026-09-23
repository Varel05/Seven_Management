<section class="space-y-6">
    <header class="flex items-start gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 border border-rose-200/80 dark:border-rose-800/50 flex items-center justify-center shrink-0 shadow-xs">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
        </div>
        <div>
            <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100">
                {{ __('Hapus Akun Pengguna') }}
            </h2>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Setelah akun dihapus, seluruh data, riwayat mutasi transaksi, dan pengaturan akun ini akan dihapus secara permanen.') }}
            </p>
        </div>
    </header>

    <div class="p-4 rounded-xl bg-rose-50/70 dark:bg-rose-950/30 border border-rose-200/80 dark:border-rose-900/50 text-xs sm:text-sm text-rose-800 dark:text-rose-300 flex items-start gap-3">
        <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <p class="leading-relaxed">
            {{ __('Perhatian: Harap unduh terlebih dahulu data laporan keuangan yang mungkin Anda butuhkan, karena tindakan penghapusan akun tidak dapat dibatalkan.') }}
        </p>
    </div>

    <div>
        <x-danger-button
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            <span>{{ __('Hapus Akun Saya') }}</span>
        </x-danger-button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-7">
            @csrf
            @method('delete')

            <div class="flex items-start gap-3.5 mb-4">
                <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 border border-rose-200/80 dark:border-rose-800/50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100">
                        {{ __('Konfirmasi Penghapusan Akun') }}
                    </h2>
                    <p class="mt-1 text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Apakah Anda yakin ingin menghapus akun ini secara permanen? Masukkan kata sandi akun Anda untuk mengonfirmasi.') }}
                    </p>
                </div>
            </div>

            <div class="mt-5">
                <x-input-label for="password" value="{{ __('Kata Sandi Konfirmasi') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full sm:w-3/4 text-xs sm:text-sm"
                    placeholder="{{ __('Masukkan kata sandi Anda') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-xs" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Batal') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Hapus Akun Permanen') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
