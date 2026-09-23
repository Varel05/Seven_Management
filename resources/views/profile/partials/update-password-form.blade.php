<section>
    <header class="flex items-start gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 border border-blue-200/80 dark:border-blue-800/50 flex items-center justify-center shrink-0 shadow-xs">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
        </div>
        <div>
            <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100">
                {{ __('Perbarui Kata Sandi') }}
            </h2>
            <p class="mt-0.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                {{ __('Pastikan akun Anda menggunakan kombinasi kata sandi yang panjang dan aman.') }}
            </p>
        </div>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Kata Sandi Saat Ini')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1.5 block w-full text-xs sm:text-sm" autocomplete="current-password" placeholder="Masukkan kata sandi saat ini" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1.5 text-xs" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('Kata Sandi Baru')" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1.5 block w-full text-xs sm:text-sm" autocomplete="new-password" placeholder="Minimal 8 karakter kombinasi" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1.5 text-xs" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Konfirmasi Kata Sandi Baru')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1.5 block w-full text-xs sm:text-sm" autocomplete="new-password" placeholder="Ulangi kata sandi baru" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1.5 text-xs" />
        </div>

        <div class="flex items-center gap-4 pt-2">
            <x-primary-button>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <span>{{ __('Perbarui Kata Sandi') }}</span>
            </x-primary-button>

            @if (session('status') === 'password-updated')
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                >
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ __('Kata sandi berhasil diperbarui.') }}</span>
                </div>
            @endif
        </div>
    </form>
</section>
