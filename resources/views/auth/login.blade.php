<x-guest-layout>
    <div 
        x-data="{ 
            loginMode: 'quick', // 'quick' or 'manual'
            selectedRole: '{{ old('email') === 'akuntan@seven.com' ? 'akuntan' : 'owner' }}',
            email: '{{ old('email', 'admin@example.com') }}',
            password: '',
            showPassword: false,

            selectAccount(role, emailAddr) {
                this.selectedRole = role;
                this.email = emailAddr;
                this.$nextTick(() => {
                    this.$refs.pinInput?.focus();
                });
            },

            fillDefaultPin() {
                this.password = '123456';
            }
        }" 
        class="relative overflow-hidden bg-white/95 dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/60 dark:shadow-none transition-all"
    >
        <!-- Top Gradient Accent -->
        <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-indigo-600"></div>

        <!-- Header -->
        <div class="mb-6 text-center">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-300/80 dark:border-emerald-800 mb-2.5 shadow-2xs">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Sistem Terbatas Internal
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Seven Management
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Pilih profil Anda dan masukkan PIN untuk akses cepat
            </p>
        </div>

        <!-- Session Status Alert -->
        @if (session('status'))
            <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf
            <!-- Always enforce remember me for 1-year persistent session -->
            <input type="hidden" name="remember" value="1">

            <!-- MODE 1: QUICK PROFILE SELECTOR (DEFAULT) -->
            <div x-show="loginMode === 'quick'" x-transition:enter="transition duration-200 ease-out" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-2 text-center sm:text-left">
                        Pilih Profil Pengguna
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Profile Card 1: OWNER -->
                        <button 
                            type="button"
                            @click="selectAccount('owner', 'admin@example.com')"
                            :class="selectedRole === 'owner' 
                                ? 'border-emerald-500 bg-emerald-50/80 dark:bg-emerald-950/30 ring-2 ring-emerald-500/30 dark:ring-emerald-500/20 shadow-md' 
                                : 'border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 hover:border-slate-300 dark:hover:border-slate-700'"
                            class="relative p-3.5 rounded-2xl border text-left transition-all duration-150 flex flex-col justify-between group cursor-pointer focus:outline-none"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300 flex items-center justify-center text-lg font-bold shadow-xs">
                                    👑
                                </span>
                                <span 
                                    x-show="selectedRole === 'owner'" 
                                    class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px]"
                                >
                                    ✓
                                </span>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-slate-900 dark:text-white leading-tight">Owner</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Seven Management</p>
                            </div>
                            <div class="mt-2.5 pt-2 border-t border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between text-[10px]">
                                <span class="font-mono text-slate-400">admin@example.com</span>
                            </div>
                        </button>

                        <!-- Profile Card 2: AKUNTAN -->
                        <button 
                            type="button"
                            @click="selectAccount('akuntan', 'akuntan@seven.com')"
                            :class="selectedRole === 'akuntan' 
                                ? 'border-emerald-500 bg-emerald-50/80 dark:bg-emerald-950/30 ring-2 ring-emerald-500/30 dark:ring-emerald-500/20 shadow-md' 
                                : 'border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 hover:border-slate-300 dark:hover:border-slate-700'"
                            class="relative p-3.5 rounded-2xl border text-left transition-all duration-150 flex flex-col justify-between group cursor-pointer focus:outline-none"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="w-9 h-9 rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-950/80 dark:text-teal-300 flex items-center justify-center text-lg font-bold shadow-xs">
                                    💼
                                </span>
                                <span 
                                    x-show="selectedRole === 'akuntan'" 
                                    class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px]"
                                >
                                    ✓
                                </span>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-slate-900 dark:text-white leading-tight">Maya Anggraini</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Staff Akuntan</p>
                            </div>
                            <div class="mt-2.5 pt-2 border-t border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between text-[10px]">
                                <span class="font-mono text-slate-400">akuntan@seven.com</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Hidden Input bound to email -->
                <input type="hidden" name="email" :value="email">

                <!-- PIN Input Section -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="pin" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                            Masukkan PIN / Kata Sandi
                        </label>
                        <button 
                            type="button" 
                            @click="fillDefaultPin()"
                            class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 underline"
                        >
                            Isi PIN Default (123456)
                        </button>
                    </div>

                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input 
                            id="pin" 
                            x-ref="pinInput"
                            :type="showPassword ? 'text' : 'password'" 
                            name="password" 
                            x-model="password"
                            required 
                            autofocus
                            placeholder="Ketik 6 digit PIN atau sandi"
                            class="w-full pl-10 pr-11 py-2.5 rounded-xl text-sm bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 tracking-wider transition-all"
                        />
                        <button 
                            type="button" 
                            @click="showPassword = !showPassword" 
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus:outline-none"
                            title="Tampilkan / Sembunyikan"
                        >
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>
                        </button>
                    </div>

                    @if ($errors->has('password') || $errors->has('email'))
                        <p class="mt-2 text-xs text-rose-600 dark:text-rose-400 font-medium flex items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>{{ $errors->first('password') ?: $errors->first('email') }}</span>
                        </p>
                    @endif
                </div>
            </div>

            <!-- MODE 2: MANUAL EMAIL & PASSWORD LOGIN (FALLBACK) -->
            <div x-show="loginMode === 'manual'" x-cloak x-transition:enter="transition duration-200 ease-out" class="space-y-4">
                <div>
                    <label for="manual_email" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                        Alamat Email
                    </label>
                    <input 
                        id="manual_email" 
                        type="email" 
                        x-model="email"
                        :name="loginMode === 'manual' ? 'email' : null"
                        placeholder="nama@email.com"
                        class="w-full px-4 py-2.5 rounded-xl text-sm bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all"
                    />
                </div>

                <div>
                    <label for="manual_password" class="block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400 mb-1.5">
                        Kata Sandi
                    </label>
                    <input 
                        id="manual_password" 
                        type="password" 
                        x-model="password"
                        :name="loginMode === 'manual' ? 'password' : null"
                        placeholder="••••••••"
                        class="w-full px-4 py-2.5 rounded-xl text-sm bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all"
                    />
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-1">
                <button 
                    type="submit" 
                    class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-sm shadow-lg shadow-emerald-600/25 dark:shadow-emerald-950/40 hover:shadow-emerald-600/35 transition-all flex items-center justify-center gap-2 group cursor-pointer"
                >
                    <span>Masuk ke Dashboard</span>
                    <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>

            <!-- Persistent Session Notice -->
            <div class="p-3 rounded-xl bg-slate-100/80 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800 text-[11px] text-slate-600 dark:text-slate-400 flex items-center gap-2.5">
                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span>Sesi aktif disimpan selama <strong>1 tahun</strong> di perangkat ini agar Anda tidak perlu login berulang kali.</span>
            </div>

            <!-- Mode Toggle Switcher -->
            <div class="text-center pt-2">
                <button 
                    type="button"
                    @click="loginMode = loginMode === 'quick' ? 'manual' : 'quick'"
                    class="text-xs font-medium text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400 transition-colors"
                >
                    <span x-show="loginMode === 'quick'">Atau masuk dengan email & kata sandi biasa</span>
                    <span x-show="loginMode === 'manual'">← Kembali ke Akses Cepat (Pilih Profil)</span>
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>
