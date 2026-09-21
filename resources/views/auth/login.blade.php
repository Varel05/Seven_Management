<x-guest-layout>
    <div 
        x-data="{ 
            showPassword: false, 
            email: '{{ old('email', '') }}', 
            password: '',
            fillDemo() {
                this.email = 'admin@example.com';
                this.password = 'password';
            }
        }" 
        class="relative overflow-hidden bg-white/95 dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/60 dark:shadow-none transition-all"
    >
        <!-- Top Gradient Accent -->
        <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-600"></div>

        <!-- Header Card -->
        <div class="mb-6 text-center sm:text-left pt-1">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-300/80 dark:border-emerald-800 mb-3 shadow-2xs">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Portal Akses Akuntan
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Masuk ke Buku Besar
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Kelola pembukuan, rekonsiliasi kas, dan mutasi otomatis Telegram
            </p>
        </div>

        <!-- Session Status Alert -->
        @if (session('status'))
            <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <!-- Email Address Field -->
            <div>
                <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                    Alamat Email
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                        </svg>
                    </div>
                    <input 
                        id="email" 
                        type="email" 
                        name="email" 
                        x-model="email"
                        required 
                        autofocus 
                        autocomplete="username" 
                        placeholder="nama@perusahaan.com"
                        class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 transition-all"
                    />
                </div>
                @if ($errors->has('email'))
                    <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400 font-medium">
                        {{ $errors->first('email') }}
                    </p>
                @endif
            </div>

            <!-- Password Field -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                        Kata Sandi
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:underline">
                            Lupa sandi?
                        </a>
                    @endif
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input 
                        id="password" 
                        :type="showPassword ? 'text' : 'password'" 
                        name="password" 
                        x-model="password"
                        required 
                        autocomplete="current-password" 
                        placeholder="••••••••"
                        class="w-full pl-10 pr-11 py-2.5 rounded-xl text-sm bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 transition-all"
                    />
                    <!-- Toggle Visibility Button -->
                    <button 
                        type="button" 
                        @click="showPassword = !showPassword" 
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus:outline-none"
                        title="Tampilkan / Sembunyikan Kata Sandi"
                    >
                        <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>
                    </button>
                </div>
                @if ($errors->has('password'))
                    <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400 font-medium">
                        {{ $errors->first('password') }}
                    </p>
                @endif
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between pt-1">
                <label for="remember_me" class="inline-flex items-center cursor-pointer">
                    <input 
                        id="remember_me" 
                        type="checkbox" 
                        name="remember" 
                        class="rounded border-slate-300 dark:border-slate-700 text-emerald-600 focus:ring-emerald-500 dark:bg-slate-800 shadow-xs w-4 h-4 transition-colors"
                    >
                    <span class="ms-2 text-xs font-medium text-slate-600 dark:text-slate-400">
                        Ingat saya di perangkat ini
                    </span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button 
                    type="submit" 
                    class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-sm shadow-lg shadow-emerald-600/25 dark:shadow-emerald-950/40 hover:shadow-emerald-600/35 transition-all flex items-center justify-center gap-2 group"
                >
                    <span>Masuk ke Buku Besar</span>
                    <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>

            <!-- Demo Account Helper Card -->
            <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800">
                <div class="p-3.5 rounded-2xl bg-gradient-to-r from-emerald-50/90 via-teal-50/50 to-slate-50 dark:from-slate-800/80 dark:to-slate-800/60 border border-emerald-200/90 dark:border-slate-700/80 flex items-center justify-between gap-3 shadow-2xs">
                    <div class="text-xs">
                        <span class="font-bold text-slate-800 dark:text-slate-200 block">Akun Uji Coba (Demo):</span>
                        <span class="text-slate-600 dark:text-slate-400 font-mono text-[11px] font-semibold">admin@example.com</span>
                    </div>
                    <button 
                        @click="fillDemo()" 
                        type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition-colors shrink-0"
                    >
                        Isi Otomatis
                    </button>
                </div>
            </div>

            <!-- Register Link if enabled -->
            @if (Route::has('register'))
                <div class="text-center pt-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Belum memiliki akun?
                        <a href="{{ route('register') }}" class="font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                            Daftar akun baru
                        </a>
                    </p>
                </div>
            @endif
        </form>
    </div>
</x-guest-layout>
