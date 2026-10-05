<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-emerald-900/80 dark:bg-slate-800 border border-emerald-700/80 dark:border-slate-700 text-emerald-200 dark:text-emerald-300 flex items-center justify-center font-bold shadow-xs backdrop-blur-md shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-2xl font-black tracking-tight text-white drop-shadow-xs">
                            {{ __('Manajemen Karyawan & Payroll') }}
                        </h1>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-900/80 dark:bg-slate-800 text-emerald-200 dark:text-emerald-300 border border-emerald-700/80 dark:border-slate-700 backdrop-blur-md shadow-2xs">
                            Akun Beban Gaji (5002)
                        </span>
                    </div>
                    <p class="text-xs text-emerald-200/80 dark:text-slate-400 mt-1 font-medium">
                        {{ __('Kelola data staf, sistem bonus berbasis poin, dan pembukuan akun Beban Gaji (5002)') }}
                    </p>
                </div>
            </div>

            <!-- Header Quick Stats & Action -->
            <div class="flex items-center gap-3">
                <div class="hidden sm:flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-emerald-900/80 dark:bg-emerald-950/60 border border-emerald-700/80 dark:border-emerald-800 text-emerald-200 dark:text-emerald-300 text-xs font-semibold shadow-xs backdrop-blur-md">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span class="font-bold text-white dark:text-slate-200">{{ $totalActive }}</span> Karyawan Aktif
                </div>
                <button 
                    type="button" 
                    @click="$dispatch('open-allowance-modal')"
                    onclick="window.dispatchEvent(new CustomEvent('open-allowance-modal'))"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-700 text-white text-xs font-bold shadow-xs transition-all hover:scale-105 active:scale-95 cursor-pointer"
                >
                    <svg class="w-4 h-4 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                    <span>Kelola Tunjangan ({{ $allowances->count() }})</span>
                </button>
                <button 
                    type="button" 
                    @click="$dispatch('open-add-employee')"
                    onclick="window.dispatchEvent(new CustomEvent('open-add-employee'))"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white text-xs font-bold shadow-md shadow-emerald-950/40 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    <span>Tambah Karyawan</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div x-data="{
        filterStatus: 'all',
        searchQuery: '',
        perPage: 10,
        currentPage: 1,
        items: {{ Js::from($employees->map(function($e) {
            return [
                'id'        => $e->id,
                'name'      => $e->name,
                'position'  => $e->position,
                'phone'     => $e->phone ?? '',
                'status'    => $e->status,
                'is_due'    => $e->isDueToday(),
                'is_paid'   => (bool) $e->isPaidThisMonth(),
                'search'    => strtolower($e->name . ' ' . $e->position . ' ' . ($e->phone ?? '') . ' ' . ($e->assetAccount->name ?? '')),
            ];
        })) }},
        get filteredItems() {
            return this.items.filter(item => {
                let matchStatus = true;
                if (this.filterStatus === 'active') matchStatus = item.status === 'active';
                else if (this.filterStatus === 'due') matchStatus = item.is_due;
                else if (this.filterStatus === 'paid') matchStatus = item.is_paid;
                const matchSearch = !this.searchQuery || item.search.includes(this.searchQuery.toLowerCase().trim());
                return matchStatus && matchSearch;
            });
        },
        get visibleCount() {
            return this.filteredItems.length;
        },
        get totalPages() {
            if (this.perPage === 'all') return 1;
            const limit = Number(this.perPage);
            return Math.max(1, Math.ceil(this.filteredItems.length / limit));
        },
        get paginatedItemIds() {
            if (this.perPage === 'all') {
                return this.filteredItems.map(i => i.id);
            }
            const limit = Number(this.perPage);
            const start = (this.currentPage - 1) * limit;
            return this.filteredItems.slice(start, start + limit).map(i => i.id);
        },
        isRowVisible(id) {
            return this.paginatedItemIds.includes(id);
        },
        get displayStart() {
            if (this.filteredItems.length === 0) return 0;
            if (this.perPage === 'all') return 1;
            return (this.currentPage - 1) * Number(this.perPage) + 1;
        },
        get displayEnd() {
            if (this.filteredItems.length === 0) return 0;
            if (this.perPage === 'all') return this.filteredItems.length;
            return Math.min(this.currentPage * Number(this.perPage), this.filteredItems.length);
        },
        goToPage(p) {
            if (p >= 1 && p <= this.totalPages) {
                this.currentPage = p;
            }
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        get pageNumbers() {
            const total = this.totalPages;
            const current = this.currentPage;
            const delta = 2;
            const range = [];
            for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
                range.push(i);
            }
            if (current - delta > 2) {
                range.unshift('...');
            }
            if (current + delta < total - 1) {
                range.push('...');
            }
            range.unshift(1);
            if (total > 1) {
                range.push(total);
            }
            return range;
        },
        init() {
            window.addEventListener('open-add-employee', () => { this.openAddModal(); });
            window.addEventListener('open-allowance-modal', () => { this.openAddAllowanceModal(); });
            this.$watch('filterStatus', () => { this.currentPage = 1; });
            this.$watch('searchQuery', () => { this.currentPage = 1; });
            this.$watch('perPage', () => { this.currentPage = 1; });
        },

        showModal: false,
        isEdit: false,
        form: {
            id: null,
            name: '',
            role: 'cs',
            position: '',
            phone: '',
            base_salary: 0,
            daily_rate: 0,
            discipline_rate: 10000,
            holiday_rate: 50000,
            current_points: 0,
            rate_per_point: 0,
            pay_day: 25,
            claim_bonus: true,
            asset_account_id: '{{ $assetAccounts->first()->id ?? '' }}',
            status: 'active'
        },
        showPointsModal: false,
        selectedEmployeeForPoints: null,
        pointsForm: {
            points: 10,
            mode: 'add'
        },
        showPayModal: false,
        employeeToPay: null,
        payForm: {
            period: '{{ now()->translatedFormat("F Y") }}',
            period_start: '{{ now()->subMonth()->setDay(26)->format("Y-m-d") }}',
            period_end: '{{ now()->setDay(25)->format("Y-m-d") }}',
            payment_method: 'Transfer',
            total_shifts: 27,
            late_count: 0,
            total_present: 27,
            discipline_present: 27,
            holiday_shifts: 0,
            daily_rate: 100000,
            discipline_rate: 10000,
            holiday_rate: 50000,
            closing_points: 0,
            closing_pcs: 0,
            sales_bonus: 0,
            notes: ''
        },
        updateDisciplinePresent() {
            let present = Number(this.payForm.total_present) || 0;
            let late = Number(this.payForm.late_count) || 0;
            this.payForm.discipline_present = Math.max(0, present - late);
        },
        calculateMainSalary() {
            return (Number(this.payForm.total_present) || 0) * (Number(this.payForm.daily_rate) || 0);
        },
        calculateDisciplineBonus() {
            return (Number(this.payForm.discipline_present) || 0) * (Number(this.payForm.discipline_rate) || 0);
        },
        calculateHolidayBonus() {
            return (Number(this.payForm.holiday_shifts) || 0) * (Number(this.payForm.holiday_rate) || 0);
        },
        calculatePayTHP() {
            let main = this.calculateMainSalary();
            let disc = this.calculateDisciplineBonus();
            let sales = Number(this.payForm.sales_bonus) || 0;
            let hol = this.calculateHolidayBonus();
            let alw = this.employeeToPay ? (Number(this.employeeToPay.total_allowance) || 0) : 0;
            return main + disc + sales + hol + alw;
        },
        showDeleteModal: false,
        employeeToDelete: null,

        showAllowanceModal: false,
        isEditAllowance: false,
        allowanceEmployeeSearch: '',
        allowanceForm: {
            id: null,
            name: '',
            target_type: 'all',
            target_role: 'cs',
            employee_id: '{{ $employees->first()->id ?? '' }}',
            target_employee_ids: [],
            amount: 100000,
            notes: '',
            is_active: true
        },
        toggleAllowanceEmployee(id) {
            id = Number(id);
            if (this.allowanceForm.target_employee_ids.includes(id)) {
                this.allowanceForm.target_employee_ids = this.allowanceForm.target_employee_ids.filter(x => x !== id);
            } else {
                this.allowanceForm.target_employee_ids.push(id);
            }
        },
        selectAllAllowanceEmployees() {
            this.allowanceForm.target_employee_ids = {{ Js::from($employees->pluck('id')->map(fn ($id) => (int) $id)->values()) }};
        },
        clearAllAllowanceEmployees() {
            this.allowanceForm.target_employee_ids = [];
        },
        openAddAllowanceModal() {
            this.isEditAllowance = false;
            this.allowanceEmployeeSearch = '';
            this.allowanceForm = {
                id: null,
                name: '',
                target_type: 'all',
                target_role: 'cs',
                employee_id: '{{ $employees->first()->id ?? '' }}',
                target_employee_ids: [],
                amount: 100000,
                notes: '',
                is_active: true
            };
            this.showAllowanceModal = true;
        },
        openEditAllowanceModal(alw) {
            this.isEditAllowance = true;
            this.allowanceEmployeeSearch = '';
            let targetIds = [];
            if (alw.target_employee_ids_list && Array.isArray(alw.target_employee_ids_list)) {
                targetIds = alw.target_employee_ids_list.map(Number);
            } else if (alw.target_employee_ids && Array.isArray(alw.target_employee_ids)) {
                targetIds = alw.target_employee_ids.map(Number);
            } else if (alw.employee_id) {
                targetIds = [Number(alw.employee_id)];
            }

            this.allowanceForm = {
                id: alw.id,
                name: alw.name,
                target_type: alw.target_type,
                target_role: alw.target_role || 'cs',
                employee_id: alw.employee_id || (targetIds[0] || ''),
                target_employee_ids: targetIds,
                amount: Number(alw.amount),
                notes: alw.notes || '',
                is_active: Boolean(alw.is_active)
            };
            this.showAllowanceModal = true;
        },

        getTierRate(points) {
            points = Number(points) || 0;
            if (points >= 500) return 2600;
            if (points >= 445) return 2200;
            if (points >= 370) return 1800;
            if (points >= 295) return 1400;
            if (points >= 200) return 1000;
            return 0;
        },
        getTierLabel(points) {
            points = Number(points) || 0;
            if (points >= 500) return 'Tier 5 (≥ 500 Poin @ Rp 2.600)';
            if (points >= 445) return 'Tier 4 (445-499 Poin @ Rp 2.200)';
            if (points >= 370) return 'Tier 3 (370-444 Poin @ Rp 1.800)';
            if (points >= 295) return 'Tier 2 (295-369 Poin @ Rp 1.400)';
            if (points >= 200) return 'Tier 1 (200-294 Poin @ Rp 1.000)';
            return '< 200 Poin (Belum Capai Tier Minimum)';
        },
        calculatePointsPreview() {
            if (!this.selectedEmployeeForPoints) return 0;
            let current = Number(this.selectedEmployeeForPoints.current_points) || 0;
            let pts = Number(this.pointsForm.points) || 0;
            if (this.pointsForm.mode === 'set') return Math.max(0, pts);
            if (this.pointsForm.mode === 'add') return current + pts;
            if (this.pointsForm.mode === 'subtract') return Math.max(0, current - pts);
            return current;
        },
        onFormPointsInput() {
            let pts = Number(this.form.current_points) || 0;
            let tr = this.getTierRate(pts);
            if (tr > 0) {
                this.form.rate_per_point = tr;
            }
        },
        openAddModal() {
            this.isEdit = false;
            this.form = {
                id: null,
                name: '',
                role: 'staff',
                position: '',
                phone: '',
                base_salary: 3000000,
                daily_rate: 0,
                discipline_rate: 10000,
                holiday_rate: 50000,
                current_points: 0,
                rate_per_point: 0,
                pay_day: 25,
                claim_bonus: true,
                asset_account_id: '{{ $assetAccounts->first()->id ?? '' }}',
                status: 'active'
            };
            this.showModal = true;
        },
        openEditModal(emp) {
            this.isEdit = true;
            this.form = {
                id: emp.id,
                name: emp.name,
                role: emp.role_value || (emp.role && emp.role.value ? emp.role.value : (emp.role || 'staff')),
                position: emp.position,
                phone: emp.phone || '',
                base_salary: Number(emp.base_salary),
                daily_rate: Number(emp.daily_rate) || 0,
                discipline_rate: Number(emp.discipline_rate) || 0,
                holiday_rate: Number(emp.holiday_rate) || 0,
                current_points: emp.current_points,
                rate_per_point: Number(emp.rate_per_point),
                pay_day: emp.pay_day,
                claim_bonus: emp.claim_bonus !== undefined ? Boolean(emp.claim_bonus) : true,
                asset_account_id: emp.asset_account_id,
                status: emp.status
            };
            this.showModal = true;
        },
        openPointsModal(emp) {
            this.selectedEmployeeForPoints = emp;
            this.pointsForm = { points: 10, mode: 'add' };
            this.showPointsModal = true;
        },
        openPayModal(emp) {
            this.employeeToPay = emp;
            let dailyRate = Number(emp.daily_rate) > 0 ? Number(emp.daily_rate) : (Number(emp.effective_daily_rate) || 100000);
            let discRate = Number(emp.discipline_rate) > 0 ? Number(emp.discipline_rate) : (Number(emp.effective_discipline_rate) || 10000);
            let holRate = Number(emp.holiday_rate) > 0 ? Number(emp.holiday_rate) : (Number(emp.effective_holiday_rate) || 50000);
            let salesBonus = emp.claim_bonus ? Number(emp.bonus_salary) : 0;

            this.payForm = {
                period: '{{ now()->translatedFormat("F Y") }}',
                period_start: '{{ now()->subMonth()->setDay(26)->format("Y-m-d") }}',
                period_end: '{{ now()->setDay(25)->format("Y-m-d") }}',
                payment_method: 'Transfer',
                total_shifts: 27,
                late_count: 0,
                total_present: 27,
                discipline_present: 27,
                holiday_shifts: 0,
                daily_rate: dailyRate,
                discipline_rate: discRate,
                holiday_rate: holRate,
                closing_points: Number(emp.current_points) || 0,
                closing_pcs: Number(emp.current_points) || 0,
                sales_bonus: salesBonus,
                notes: ''
            };
            this.showPayModal = true;
        },
        openDeleteModal(emp) {
            this.employeeToDelete = emp;
            this.showDeleteModal = true;
        }
    }" @open-add-employee.window="openAddModal()" @open-allowance-modal.window="openAddAllowanceModal()" class="py-8">
        <div class="max-w-[1800px] w-full mx-auto px-2 sm:px-4 lg:px-6 space-y-8">

            <!-- Flash Notifications -->
            @if(session('success'))
                <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-900 dark:text-emerald-200 text-sm flex items-center gap-3 shadow-sm">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-rose-900 dark:text-rose-200 text-sm flex items-start gap-3 shadow-sm">
                    <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- 5 SUMMARY METRIC CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Card 1: Total Karyawan Aktif -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-blue-300 dark:hover:border-slate-700 transition-all group flex flex-col justify-between">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-blue-300 uppercase tracking-wider">Karyawan Aktif</span>
                        <div class="p-2.5 rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-600 dark:text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight text-slate-900 dark:text-white">
                            {{ $totalActive }} <span class="text-sm font-sans font-normal text-slate-500 dark:text-slate-400">Orang</span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                            <span>Staf terdaftar aktif</span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Total Gaji Pokok -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-slate-700 transition-all group flex flex-col justify-between">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-indigo-600 z-10" style="background: #4f46e5;"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-indigo-300 uppercase tracking-wider">Total Gaji Pokok</span>
                        <div class="p-2.5 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-600 dark:text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight text-slate-900 dark:text-white">
                            Rp {{ number_format($totalBaseSalary, 0, ',', '.') }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                            <span>Gaji pokok tetap bulanan</span>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Total Tunjangan -->
                <div @click="openAddAllowanceModal()" class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-teal-300 dark:hover:border-slate-700 transition-all group flex flex-col justify-between cursor-pointer">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-teal-500 to-emerald-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-teal-300 uppercase tracking-wider">Total Tunjangan</span>
                        <div class="p-2.5 rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-600 dark:text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight text-teal-700 dark:text-teal-300">
                            Rp {{ number_format($totalAllowanceEstimate, 0, ',', '.') }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
                            <span>{{ $allowances->where('is_active', true)->count() }} jenis tunjangan aktif</span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Total Bonus Poin -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-amber-300 dark:hover:border-slate-700 transition-all group flex flex-col justify-between">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-amber-500 to-orange-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-amber-300 uppercase tracking-wider">Bonus Kinerja (Poin)</span>
                        <div class="p-2.5 rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500 dark:text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight text-amber-600 dark:text-amber-400">
                            Rp {{ number_format($totalBonusSalary, 0, ',', '.') }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            <span>{{ $employees->sum('current_points') }} total poin periode ini</span>
                        </div>
                    </div>
                </div>

                <!-- Card 5: Total Estimasi Payroll -->
                <div class="relative overflow-hidden bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-emerald-300 dark:hover:border-slate-700 transition-all group flex flex-col justify-between">
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 to-teal-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-emerald-300 uppercase tracking-wider">Total Payroll Bulan Ini</span>
                        <div class="p-2.5 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-600 dark:text-white shadow-xs group-hover:scale-105 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                    <div class="mt-3.5">
                        <div class="text-2xl font-extrabold font-mono tracking-tight text-emerald-700 dark:text-emerald-400">
                            Rp {{ number_format($totalPayrollEstimate, 0, ',', '.') }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>Beban Gaji (Akun 5002)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. DAFTAR KARYAWAN & STATUS PENGGAJIAN TABLE -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                
                <!-- Table Controls Header (Search & Status Tabs) -->
                <div class="p-4 sm:p-5 border-b border-slate-200/80 dark:border-slate-800 flex flex-col xl:flex-row xl:items-center justify-between gap-3.5 bg-slate-50/50 dark:bg-slate-900">
                    <div class="flex items-center gap-3 shrink-0">
                        <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-600/20 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                <span>Daftar Karyawan & Status Penggajian</span>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800" x-text="filteredItems.length + ' staf'"></span>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Kelola gaji, poin bonus & pembukuan payroll.
                            </p>
                        </div>
                    </div>

                    <!-- Search & Filter Controls -->
                    <div class="flex flex-wrap lg:flex-nowrap items-center gap-2.5">
                        <!-- Per-Page Limit Selector -->
                        <div class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 shrink-0">
                            <span class="hidden 2xl:inline font-medium">Batas:</span>
                            <select 
                                x-model="perPage" 
                                class="py-1.5 pl-2.5 pr-7 text-xs font-semibold rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 shadow-2xs cursor-pointer transition-all"
                                title="Batas maksimal data per halaman"
                            >
                                <option value="5">5 baris</option>
                                <option value="10">10 baris</option>
                                <option value="25">25 baris</option>
                                <option value="50">50 baris</option>
                                <option value="all">Semua</option>
                            </select>
                        </div>

                        <!-- Live Search Input -->
                        <div class="relative shrink-0">
                            <input 
                                x-model="searchQuery" 
                                type="text" 
                                placeholder="Cari staf, jabatan, akun..." 
                                class="w-44 sm:w-52 pl-8 pr-3 py-1.5 text-xs rounded-xl bg-white dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:focus:ring-emerald-400 shadow-xs transition-all"
                            />
                            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>

                        <!-- Status Filter Tabs with Vivid Color Coding -->
                        <div class="inline-flex rounded-xl bg-slate-200/70 dark:bg-slate-800 p-1 border border-slate-300/60 dark:border-slate-700 text-xs font-medium shrink-0">
                            <button 
                                @click="filterStatus = 'all'" 
                                :class="filterStatus === 'all' ? 'bg-slate-900 text-white shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'"
                                class="px-2.5 py-1 rounded-lg transition-all"
                            >
                                Semua
                            </button>
                            <button 
                                @click="filterStatus = 'active'" 
                                :class="filterStatus === 'active' ? 'bg-emerald-600 text-white shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-emerald-700 dark:hover:text-emerald-400'"
                                class="px-2.5 py-1 rounded-lg transition-all"
                            >
                                Aktif
                            </button>
                            <button 
                                @click="filterStatus = 'due'" 
                                :class="filterStatus === 'due' ? 'bg-amber-500 text-white shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-amber-700 dark:hover:text-amber-400'"
                                class="px-2.5 py-1 rounded-lg transition-all"
                            >
                                Jatuh Tempo
                            </button>
                            <button 
                                @click="filterStatus = 'paid'" 
                                :class="filterStatus === 'paid' ? 'bg-teal-600 text-white shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-teal-700 dark:hover:text-teal-400'"
                                class="px-2.5 py-1 rounded-lg transition-all"
                            >
                                Lunas Bulan Ini
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gradient-to-r from-slate-100 via-slate-50 to-slate-100 dark:from-slate-800/80 dark:to-slate-800/60 border-b border-slate-200/90 dark:border-slate-800 text-[10px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                <th class="px-2.5 py-2.5 min-w-[140px]">Karyawan</th>
                                <th class="px-2 py-2.5 whitespace-nowrap">Gaji Pokok</th>
                                <th class="px-2 py-2.5 whitespace-nowrap">Tunjangan</th>
                                <th class="px-2 py-2.5 whitespace-nowrap">Poin Kinerja</th>
                                <th class="px-2 py-2.5 whitespace-nowrap">Bonus Poin</th>
                                <th class="px-2 py-2.5 whitespace-nowrap text-right">Total Gaji</th>
                                <th class="px-2 py-2.5 whitespace-nowrap text-center">Jatuh Tempo</th>
                                <th class="px-2 py-2.5 whitespace-nowrap">Akun Bayar</th>
                                <th class="px-2 py-2.5 whitespace-nowrap text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/80 text-xs">
                            @forelse($employees as $emp)
                                @php
                                    $isDue = $emp->isDueToday();
                                    $isPaid = $emp->isPaidThisMonth();
                                    $paidDate = $emp->last_paid_at ?? $emp->findExistingCurrentMonthPayrollJournal()?->date;
                                @endphp
                                <tr 
                                    x-show="isRowVisible({{ $emp->id }})"
                                    class="hover:bg-emerald-50/40 dark:hover:bg-slate-800/40 transition-colors group {{ $isDue ? 'bg-amber-50/30 dark:bg-amber-950/20' : '' }}"
                                >
                                    <!-- Karyawan (3 Baris: Nama+#ID, Posisi, No HP) -->
                                    <td class="px-2.5 py-2 whitespace-nowrap">
                                        <div class="flex items-start gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-800 dark:to-slate-700 text-slate-800 dark:text-slate-200 font-extrabold flex items-center justify-center text-[10px] shadow-2xs border border-slate-200 dark:border-slate-700 shrink-0 mt-0.5">
                                                {{ strtoupper(substr($emp->name, 0, 2)) }}
                                            </div>
                                            <div class="space-y-0.5">
                                                <!-- Baris 1: Nama, ID & Role Badge -->
                                                <div class="font-bold text-slate-900 dark:text-slate-100 text-xs flex items-center gap-1.5 flex-wrap leading-tight">
                                                    <span>{{ $emp->name }}</span>
                                                    <span class="font-mono text-[9px] font-bold px-1 py-0.2 rounded {{ $emp->status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                                        #{{ $emp->id }}
                                                    </span>
                                                    <span class="text-[9px] font-bold px-1.5 py-0.2 rounded border {{ $emp->role_badge_class }}">
                                                        {{ $emp->role_label }}
                                                    </span>
                                                </div>
                                                <!-- Baris 2: Jabatan / Posisi -->
                                                <div class="text-[10px] font-medium text-slate-600 dark:text-slate-400 leading-tight">
                                                    {{ $emp->position }}
                                                </div>
                                                <!-- Baris 3: No. Telepon -->
                                                @if($emp->phone)
                                                    <div class="text-[9px] text-slate-400 dark:text-slate-500 font-mono leading-tight">
                                                        {{ $emp->phone }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Gaji Pokok -->
                                    <td class="px-2 py-2 whitespace-nowrap font-mono font-bold text-slate-900 dark:text-slate-100 text-xs">
                                        {{ $emp->formatted_base_salary }}
                                    </td>

                                    <!-- Tunjangan -->
                                    <td class="px-2 py-2 whitespace-nowrap text-xs">
                                        @if($emp->total_allowance > 0)
                                            <div class="space-y-0.5">
                                                <span class="font-mono font-bold text-teal-700 dark:text-teal-300">
                                                    {{ $emp->formatted_total_allowance }}
                                                </span>
                                                <div class="text-[9px] text-slate-500 dark:text-slate-400 truncate max-w-[95px]" title="{{ $emp->applicable_allowances->pluck('name')->implode(', ') }}">
                                                    {{ $emp->applicable_allowances->pluck('name')->implode(', ') }}
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-slate-400 dark:text-slate-600 font-mono text-[11px]">-</span>
                                        @endif
                                    </td>

                                    <!-- Poin Kinerja -->
                                    <td class="px-2 py-2 whitespace-nowrap text-xs">
                                        <div class="inline-flex items-center gap-1">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-bold border border-amber-200 dark:border-amber-800 text-[10px] shadow-2xs">
                                                ⭐ {{ $emp->current_points }} Poin
                                            </span>
                                            <button 
                                                type="button" 
                                                @click="openPointsModal({{ Js::from($emp) }})" 
                                                class="p-0.5 rounded text-amber-600 hover:text-amber-700 dark:text-amber-400 hover:bg-amber-100/70 dark:hover:bg-amber-900/50 transition-colors cursor-pointer" 
                                                title="Kelola Poin Bonus"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                            </button>
                                        </div>
                                        <div class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5 font-mono flex items-center gap-1 flex-wrap">
                                            <span>@ Rp {{ number_format($emp->rate_per_point, 0, ',', '.') }}/pt</span>
                                            @if($emp->current_points >= 200)
                                                <span class="inline-block px-1 py-0.2 rounded bg-amber-100/80 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 font-bold text-[9px]" title="{{ $emp->tier_label }}">{{ $emp->tier_name }}</span>
                                            @endif
                                            @if(!$emp->claim_bonus)
                                                <span class="inline-block px-1 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-bold text-[9px]" title="Poin disimpan (tidak diklaim bulan ini)">💤 Simpan</span>
                                            @else
                                                <span class="inline-block px-1 py-0.2 rounded bg-emerald-100/80 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-bold text-[9px]" title="Poin akan dicairkan saat gajian">⚡ Klaim</span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Bonus Poin -->
                                    <td class="px-2 py-2 whitespace-nowrap font-mono font-bold text-amber-600 dark:text-amber-400 text-xs">
                                        {{ $emp->formatted_bonus_salary }}
                                    </td>

                                    <!-- Total Gaji -->
                                    <td class="px-2 py-2 whitespace-nowrap text-right font-mono font-extrabold text-xs">
                                        <span class="inline-block px-1.5 py-0.5 rounded-md text-emerald-700 dark:text-emerald-400 bg-emerald-50/90 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-900/50 shadow-2xs text-[11px]">
                                            {{ $emp->formatted_total_salary }}
                                        </span>
                                    </td>

                                    <!-- Jatuh Tempo -->
                                    <td class="px-2 py-2 whitespace-nowrap text-center text-xs">
                                        <div class="font-bold text-slate-800 dark:text-slate-200 text-[11px]">
                                            Tgl {{ $emp->pay_day }} {{ now()->translatedFormat('M') }}
                                        </div>
                                        <div class="mt-0.5">
                                            @if($isPaid)
                                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-950/60 px-1.5 py-0.2 rounded-full border border-emerald-300/80 dark:border-emerald-800">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                    Lunas ({{ $paidDate ? \Carbon\Carbon::parse($paidDate)->translatedFormat('d M') : 'Bulan ini' }})
                                                </span>
                                            @elseif($isDue)
                                                <span class="inline-flex items-center gap-1 text-[9px] font-bold text-amber-900 dark:text-amber-300 bg-amber-100 dark:bg-amber-950/60 px-1.5 py-0.2 rounded-full border border-amber-300/80 dark:border-amber-800 animate-pulse">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                    Jatuh Tempo!
                                                </span>
                                            @else
                                                <span class="inline-flex items-center text-[9px] font-medium text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.2 rounded-full border border-slate-200 dark:border-slate-700">
                                                    Belum dibayar
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Akun Pembayaran -->
                                    <td class="px-2 py-2 whitespace-nowrap text-xs">
                                        <div class="inline-flex items-center gap-1 max-w-[110px]">
                                            <span class="font-mono text-[9px] font-bold px-1 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shrink-0">
                                                {{ $emp->assetAccount->code ?? '1001' }}
                                            </span>
                                            <span class="truncate text-slate-700 dark:text-slate-300 font-medium text-[11px]" title="{{ $emp->assetAccount->name ?? 'Kas Operasional' }}">
                                                {{ $emp->assetAccount->name ?? 'Kas Operasional' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Aksi (Tombol Edit & Hapus di atas, Tombol Bayar di bawah) -->
                                    <td class="px-2 py-2 whitespace-nowrap text-center text-xs">
                                        <div class="inline-flex flex-col items-center justify-center gap-1">
                                            <div class="flex items-center justify-center gap-1">
                                                <button 
                                                    type="button" 
                                                    @click="openEditModal({{ Js::from($emp) }})" 
                                                    class="inline-flex items-center justify-center w-6 h-6 rounded-md text-slate-600 hover:text-blue-600 bg-slate-100 hover:bg-blue-50 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-200 hover:border-blue-300 dark:border-slate-700 transition-all shadow-2xs cursor-pointer"
                                                    title="Edit Data Karyawan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>

                                                <button 
                                                    type="button" 
                                                    @click="openDeleteModal({{ Js::from($emp) }})" 
                                                    class="inline-flex items-center justify-center w-6 h-6 rounded-md text-slate-600 hover:text-rose-600 bg-slate-100 hover:bg-rose-50 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 border border-slate-200 hover:border-rose-300 dark:border-slate-700 transition-all shadow-2xs cursor-pointer"
                                                    title="Hapus Karyawan"
                                                >
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>

                                            @if(!$isPaid)
                                                <button 
                                                    type="button" 
                                                    @click="openPayModal({{ Js::from($emp) }})" 
                                                    class="w-full inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-bold text-white bg-emerald-600 hover:bg-emerald-500 shadow-xs shadow-emerald-600/20 transition-all hover:scale-105 active:scale-95 cursor-pointer"
                                                    title="Input Absensi & Hitung Slip Gaji"
                                                >
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                    <span>Bayar</span>
                                                </button>
                                            @else
                                                @if($emp->latestPayroll)
                                                    <a 
                                                        href="{{ route('employees.payroll.slip', $emp->latestPayroll) }}" 
                                                        target="_blank"
                                                        class="w-full inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded-md text-[9px] font-bold text-teal-700 dark:text-teal-300 bg-teal-50 dark:bg-teal-950/80 border border-teal-200 dark:border-teal-800 shadow-2xs hover:bg-teal-100 transition-all cursor-pointer"
                                                        title="Lihat / Cetak Lembar Slip Gaji Excel"
                                                    >
                                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                        <span>Slip</span>
                                                    </a>
                                                @else
                                                    <button 
                                                        type="button" 
                                                        @click="openPayModal({{ Js::from($emp) }})" 
                                                        class="w-full inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded-md text-[9px] font-bold text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition-all cursor-pointer"
                                                        title="Buat Lembar Slip Gaji Baru"
                                                    >
                                                        <span>+ Slip</span>
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <!-- Kasus 1: Database Kosong -->
                                <tr>
                                    <td colspan="9" class="px-6 py-14 text-center">
                                        <div class="max-w-md mx-auto flex flex-col items-center">
                                            <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-3.5 border border-amber-200/80 dark:border-amber-800/60 shadow-xs">
                                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                            </div>
                                            <h4 class="text-base font-bold text-slate-900 dark:text-slate-100">Belum Ada Data Karyawan</h4>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                                                Belum ada data staf atau karyawan yang terdaftar. Tambahkan data karyawan untuk mengelola sistem poin, gaji pokok, dan pembukuan payroll otomatis.
                                            </p>
                                            <button 
                                                type="button" 
                                                @click="openAddModal()" 
                                                class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition-all"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                <span>Tambah Karyawan Pertama</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse

                            <!-- Kasus 2: Data Ada di Database, tetapi Tidak Ditemukan pada Filter / Pencarian Tertentu -->
                            @if($employees->isNotEmpty())
                                <tr x-show="visibleCount === 0" x-cloak>
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <div class="max-w-md mx-auto flex flex-col items-center">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center mb-3 border border-slate-200 dark:border-slate-700 shadow-xs">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                            </div>
                                            <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">Data Karyawan Tidak Ditemukan</h4>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                Tidak ditemukan data karyawan yang cocok dengan kata kunci atau filter status yang dipilih.
                                            </p>
                                            <button 
                                                type="button" 
                                                @click="filterStatus = 'all'; searchQuery = ''" 
                                                class="mt-3.5 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:hover:bg-emerald-900 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition-colors shadow-2xs"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                                Reset Filter & Pencarian
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Table Navigation & Pagination Footer -->
                @if($employees->isNotEmpty())
                    <div 
                        x-show="filteredItems.length > 0" 
                        class="px-5 py-3.5 border-t border-slate-200/80 dark:border-slate-800 bg-gradient-to-r from-slate-50/80 via-white to-slate-50/80 dark:from-slate-900 dark:to-slate-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                    >
                        <!-- Rentang & Total Data Karyawan -->
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <span class="inline-flex w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>
                                Menampilkan 
                                <strong class="font-bold text-slate-900 dark:text-slate-100" x-text="displayStart"></strong> 
                                - 
                                <strong class="font-bold text-slate-900 dark:text-slate-100" x-text="displayEnd"></strong> 
                                dari 
                                <strong class="font-bold text-slate-900 dark:text-slate-100" x-text="filteredItems.length"></strong> 
                                data karyawan
                            </span>
                            <span class="hidden md:inline text-slate-300 dark:text-slate-700">|</span>
                            <span class="hidden md:inline font-medium" x-text="'Halaman ' + currentPage + ' dari ' + totalPages"></span>
                        </div>

                        <!-- Kontrol Navigasi Tombol & Halaman -->
                        <div class="flex items-center gap-1.5 self-center sm:self-auto" x-show="totalPages > 1 || perPage !== 'all'">
                            <!-- Tombol Sebelumnya -->
                            <button 
                                type="button" 
                                @click="prevPage()" 
                                :disabled="currentPage === 1"
                                :class="currentPage === 1 ? 'opacity-40 cursor-not-allowed text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-slate-800/50' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 bg-white dark:bg-slate-800 shadow-2xs'"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 transition-all"
                                title="Halaman sebelumnya"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                <span>Sebelumnya</span>
                            </button>

                            <!-- Nomor Halaman Dinamis -->
                            <div class="flex items-center gap-1">
                                <template x-for="(p, index) in pageNumbers" :key="index">
                                    <div class="flex items-center">
                                        <template x-if="p === '...'">
                                            <span class="px-1.5 py-1 text-xs text-slate-400 dark:text-slate-500 font-mono">...</span>
                                        </template>
                                        <template x-if="p !== '...'">
                                            <button 
                                                type="button" 
                                                @click="goToPage(p)" 
                                                :class="currentPage === p 
                                                    ? 'bg-emerald-600 text-white font-extrabold shadow-sm shadow-emerald-600/30 border-emerald-600' 
                                                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 font-semibold'"
                                                class="w-8 h-8 flex items-center justify-center text-xs rounded-xl border transition-all"
                                                x-text="p"
                                            ></button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- Tombol Selanjutnya -->
                            <button 
                                type="button" 
                                @click="nextPage()" 
                                :disabled="currentPage >= totalPages"
                                :class="currentPage >= totalPages ? 'opacity-40 cursor-not-allowed text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-slate-800/50' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-700 dark:hover:text-emerald-400 bg-white dark:bg-slate-800 shadow-2xs'"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 transition-all"
                                title="Halaman berikutnya"
                            >
                                <span>Selanjutnya</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- MODAL TAMBAH / EDIT KARYAWAN -->
            <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="min-h-screen px-4 text-center flex items-center justify-center">
                    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" @click="showModal = false"></div>
                    <div class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl relative z-10">
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white" x-text="isEdit ? 'Edit Data Karyawan' : 'Tambah Karyawan Baru'"></h3>
                            <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form :action="isEdit ? '{{ url('employees') }}/' + form.id : '{{ route('employees.store') }}'" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <template x-if="isEdit">
                                <input type="hidden" name="_method" value="PUT">
                            </template>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Nama Lengkap</label>
                                    <input type="text" name="name" x-model="form.name" required class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="Contoh: Budi Santoso">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Peran / Role Sistem</label>
                                    <select name="role" x-model="form.role" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                        <optgroup label="👑 Di Atas Staff (Manajemen & Pimpinan)">
                                            <option value="owner">Owner / Pemilik</option>
                                            <option value="manager">Manager / Pengelola</option>
                                            <option value="akuntan">Akuntan / Finance</option>
                                            <option value="hrd">HRD / Personalia</option>
                                            <option value="supervisor">Supervisor / Pengawas</option>
                                        </optgroup>
                                        <optgroup label="💼 Tingkat Staff (Pelaksana & Operasional)">
                                            <option value="staff">Staff Umum</option>
                                            <option value="cs">Customer Service (CS)</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Jabatan / Posisi</label>
                                    <input type="text" name="position" x-model="form.position" required class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="Contoh: Staff IT, Admin">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">No. Kontak / HP</label>
                                    <input type="text" name="phone" x-model="form.phone" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="08123456789">
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 block">📱 Nomor HP aktif karyawan</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Gaji Pokok Acuan (Rp)</label>
                                    <input type="number" step="any" name="base_salary" x-model="form.base_salary" required class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none font-mono">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Upah per Hari / Shift (Rp)</label>
                                    <input type="number" step="any" name="daily_rate" x-model="form.daily_rate" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none font-mono" placeholder="Otomatis: Gaji Pokok ÷ 27">
                                    <span class="text-[10px] text-slate-400 mt-0.5 block">Kosongkan jika dihitung otomatis (Gaji Pokok ÷ 27)</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Bonus Disiplin / Hari (Rp)</label>
                                    <input type="number" step="any" name="discipline_rate" x-model="form.discipline_rate" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none font-mono" placeholder="10000">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Bonus Tanggal Merah (Rp)</label>
                                    <input type="number" step="any" name="holiday_rate" x-model="form.holiday_rate" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none font-mono" placeholder="50000">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Poin Saat Ini</label>
                                    <input type="number" min="0" name="current_points" x-model="form.current_points" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none font-mono" placeholder="0">
                                </div>

                                <!-- Hidden rate_per_point (otomatis diatur oleh backend) -->
                                <input type="hidden" name="rate_per_point" :value="form.rate_per_point">

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Tgl Gajian (Jatuh Tempo)</label>
                                    <input type="number" min="1" max="31" name="pay_day" x-model="form.pay_day" required class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="25">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Akun Kas/Bank Pembayaran</label>
                                    <select name="asset_account_id" x-model="form.asset_account_id" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                        @foreach($assetAccounts as $acc)
                                            <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->code }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Status Karyawan</label>
                                    <select name="status" x-model="form.status" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                        <option value="active">Aktif</option>
                                        <option value="inactive">Nonaktif</option>
                                    </select>
                                </div>

                                <div class="sm:col-span-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/80 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Klaim Bonus Poin Bulan Ini</span>
                                            <span class="text-[10px] text-slate-500 dark:text-slate-400">Jika dinonaktifkan, poin disimpan dan tidak dipotong saat gajian</span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="hidden" name="claim_bonus" value="0">
                                            <input type="checkbox" name="claim_bonus" value="1" x-model="form.claim_bonus" class="sr-only peer">
                                            <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-slate-600 peer-checked:bg-emerald-600"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs">
                                <div class="text-slate-500 dark:text-slate-400">Akun Beban Akuntansi:</div>
                                <div class="font-bold text-emerald-600 dark:text-emerald-400">5002 - Beban Gaji</div>
                            </div>

                            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                                <button type="button" @click="showModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Batal</button>
                                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-sm transition-transform active:scale-95" x-text="isEdit ? 'Simpan Perubahan' : 'Tambah Karyawan'"></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- MODAL KELOLA POIN KINERJA -->
            <div x-show="showPointsModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="min-h-screen px-4 text-center flex items-center justify-center">
                    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" @click="showPointsModal = false"></div>
                    <div class="inline-block w-full max-w-sm p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl relative z-10">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                            <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Kelola Poin Kinerja</h3>
                            <button @click="showPointsModal = false" class="text-slate-400 hover:text-slate-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <template x-if="selectedEmployeeForPoints">
                            <form :action="'{{ url('employees') }}/' + selectedEmployeeForPoints.id + '/points'" method="POST" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="_method" value="PATCH">

                                <div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Karyawan:</div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-white" x-text="selectedEmployeeForPoints.name"></div>
                                    <div class="text-[11px] text-amber-600 dark:text-amber-400 mt-0.5" x-text="'Poin saat ini: ' + selectedEmployeeForPoints.current_points + ' poin'"></div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Aksi Poin</label>
                                    <select name="mode" x-model="pointsForm.mode" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                        <option value="add">➕ Tambah Poin</option>
                                        <option value="subtract">➖ Kurangi Poin</option>
                                        <option value="set">✏️ Atur Jumlah Poin Baru</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">Jumlah Poin</label>
                                    <input type="number" min="0" name="points" x-model="pointsForm.points" required class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none font-mono">
                                </div>

                                <!-- Live Tier & Bonus Preview -->
                                <div class="p-3 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/70 dark:border-amber-800/40 text-xs space-y-1.5 font-mono">
                                    <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                        <span>Estimasi Poin Akhir:</span>
                                        <span class="font-bold text-amber-600 dark:text-amber-400" x-text="calculatePointsPreview() + ' poin'"></span>
                                    </div>
                                    <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                        <span>Tarif Tier:</span>
                                        <span class="font-bold text-slate-800 dark:text-slate-200" x-text="'Rp ' + (getTierRate(calculatePointsPreview()) || (selectedEmployeeForPoints ? selectedEmployeeForPoints.rate_per_point : 0)).toLocaleString('id-ID') + '/poin'"></span>
                                    </div>
                                    <div class="flex items-center justify-between text-slate-800 dark:text-slate-200 pt-1.5 border-t border-amber-200/50 dark:border-amber-800/40 font-bold">
                                        <span>Total Bonus Poin:</span>
                                        <span class="text-emerald-600 dark:text-emerald-400" x-text="'Rp ' + (calculatePointsPreview() * (getTierRate(calculatePointsPreview()) || (selectedEmployeeForPoints ? selectedEmployeeForPoints.rate_per_point : 0))).toLocaleString('id-ID')"></span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                                    <button type="button" @click="showPointsModal = false" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Batal</button>
                                    <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold shadow-sm">Simpan Poin</button>
                                </div>
                            </form>
                        </template>
                    </div>
                </div>
            </div>

            <!-- MODAL HITUNG & BUKUKAN GAJI (SLIP GAJI SESUAI EXCEL) -->
            <div x-show="showPayModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="min-h-screen px-4 py-8 text-center flex items-center justify-center">
                    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" @click="showPayModal = false"></div>
                    <div class="inline-block w-full max-w-2xl p-6 my-4 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl relative z-10">
                        
                        <!-- Header Modal -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/20">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span>Hitung & Bukukan Gaji</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-semibold border border-emerald-300/60" x-text="employeeToPay ? employeeToPay.name : ''"></span>
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        Perhitungan slip gaji bulanan (Cut-off 26 - 25) & input absensi manual.
                                    </p>
                                </div>
                            </div>
                            <button type="button" @click="showPayModal = false" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <template x-if="employeeToPay">
                            <form :action="'{{ url('employees') }}/' + employeeToPay.id + '/pay'" method="POST" class="mt-4 space-y-4">
                                @csrf

                                <!-- Informasi Periode & Metode -->
                                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1">Periode Slip</label>
                                            <input type="text" name="period" x-model="payForm.period" required class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-medium focus:ring-2 focus:ring-emerald-500">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1">Tgl Cut-off Mulai</label>
                                            <input type="date" name="period_start" x-model="payForm.period_start" required class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-emerald-500">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1">Tgl Cut-off Akhir</label>
                                            <input type="date" name="period_end" x-model="payForm.period_end" required class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-emerald-500">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-slate-200/60 dark:border-slate-700/60">
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1">Metode Pembayaran</label>
                                            <select name="payment_method" x-model="payForm.payment_method" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                                                <option value="Transfer">Transfer Bank</option>
                                                <option value="Tunai">Tunai (Cash)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1">Akun Kas / Bank (Kredit)</label>
                                            <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 py-2 px-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 truncate" x-text="employeeToPay.asset_account ? employeeToPay.asset_account.name : 'Kas Operasional'"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bagian 1: Input Absensi Bulanan (MANUAL INPUT) -->
                                <div class="p-3.5 rounded-2xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-800/40 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                            <span class="text-xs font-bold text-amber-900 dark:text-amber-300 uppercase tracking-wider">Rekap Absensi Bulanan (Input Manual)</span>
                                        </div>
                                        <span class="text-[10px] text-amber-700 dark:text-amber-400 italic">Diisi manual dari rekap absensi HRD</span>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Total Shift</label>
                                            <input type="number" min="0" name="total_shifts" x-model.number="payForm.total_shifts" class="w-full text-xs font-mono text-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-amber-500">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-emerald-700 dark:text-emerald-400 mb-1">Total Hadir</label>
                                            <input type="number" min="0" name="total_present" x-model.number="payForm.total_present" @input="updateDisciplinePresent()" class="w-full text-xs font-mono font-bold text-center rounded-xl border border-emerald-300 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-950/40 px-2 py-2 text-emerald-900 dark:text-emerald-200 focus:ring-2 focus:ring-emerald-500">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-rose-700 dark:text-rose-400 mb-1">Terlambat</label>
                                            <input type="number" min="0" name="late_count" x-model.number="payForm.late_count" @input="updateDisciplinePresent()" class="w-full text-xs font-mono text-center rounded-xl border border-rose-300 dark:border-rose-700 bg-rose-50/50 dark:bg-rose-950/40 px-2 py-2 text-rose-900 dark:text-rose-200 focus:ring-2 focus:ring-rose-500">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-teal-700 dark:text-teal-400 mb-1">Hadir Disiplin</label>
                                            <input type="number" min="0" name="discipline_present" x-model.number="payForm.discipline_present" class="w-full text-xs font-mono font-bold text-center rounded-xl border border-teal-300 dark:border-teal-700 bg-teal-50/50 dark:bg-teal-950/40 px-2 py-2 text-teal-900 dark:text-teal-200 focus:ring-2 focus:ring-teal-500" title="Total Hadir dikurangi Terlambat">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-indigo-700 dark:text-indigo-400 mb-1">Tgl Merah</label>
                                            <input type="number" min="0" name="holiday_shifts" x-model.number="payForm.holiday_shifts" class="w-full text-xs font-mono text-center rounded-xl border border-indigo-300 dark:border-indigo-700 bg-indigo-50/50 dark:bg-indigo-950/40 px-2 py-2 text-indigo-900 dark:text-indigo-200 focus:ring-2 focus:ring-indigo-500">
                                        </div>
                                    </div>
                                </div>

                                <!-- Bagian 2: Tarif & Komponen Honorarium -->
                                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-3">
                                    <div class="flex items-center justify-between pb-2 border-b border-slate-200/60 dark:border-slate-700/60">
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Komponen & Rincian Gaji</div>
                                        <div class="text-[10px] text-slate-500 dark:text-slate-400">Live Breakdown Sesuai Slip Excel</div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <!-- Upah Harian -->
                                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 space-y-1">
                                            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                                                <span>Upah Harian (Tarif):</span>
                                                <input type="number" name="daily_rate" x-model.number="payForm.daily_rate" class="w-28 text-right font-mono text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1 text-slate-900 dark:text-white">
                                            </div>
                                            <div class="flex items-center justify-between font-semibold pt-1 border-t border-slate-100 dark:border-slate-800">
                                                <span class="text-slate-700 dark:text-slate-300">1. Honor Utama (<span x-text="payForm.total_present"></span> Hari):</span>
                                                <span class="font-mono text-slate-900 dark:text-white font-bold" x-text="'Rp ' + calculateMainSalary().toLocaleString('id-ID')"></span>
                                            </div>
                                        </div>

                                        <!-- Bonus Disiplin -->
                                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 space-y-1">
                                            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                                                <span>Tarif Disiplin / Hari:</span>
                                                <input type="number" name="discipline_rate" x-model.number="payForm.discipline_rate" class="w-24 text-right font-mono text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1 text-slate-900 dark:text-white">
                                            </div>
                                            <div class="flex items-center justify-between font-semibold pt-1 border-t border-slate-100 dark:border-slate-800">
                                                <span class="text-slate-700 dark:text-slate-300">2. Bonus Disiplin (<span x-text="payForm.discipline_present"></span> Hari):</span>
                                                <span class="font-mono text-teal-600 dark:text-teal-400 font-bold" x-text="'Rp ' + calculateDisciplineBonus().toLocaleString('id-ID')"></span>
                                            </div>
                                        </div>

                                        <!-- Bonus Penjualan / Poin -->
                                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 space-y-1">
                                            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                                                <span>Poin Closing:</span>
                                                <input type="number" name="closing_points" x-model.number="payForm.closing_points" class="w-20 text-right font-mono text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1 text-slate-900 dark:text-white">
                                            </div>
                                            <div class="flex items-center justify-between font-semibold pt-1 border-t border-slate-100 dark:border-slate-800">
                                                <span class="text-slate-700 dark:text-slate-300">3. Bonus Penjualan:</span>
                                                <div class="flex items-center gap-1">
                                                    <span class="text-slate-400 font-mono text-[11px]">Rp</span>
                                                    <input type="number" name="sales_bonus" x-model.number="payForm.sales_bonus" class="w-28 text-right font-mono text-xs font-bold text-amber-600 dark:text-amber-400 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bonus Tanggal Merah -->
                                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 space-y-1">
                                            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                                                <span>Tarif Tgl Merah / Shift:</span>
                                                <input type="number" name="holiday_rate" x-model.number="payForm.holiday_rate" class="w-24 text-right font-mono text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-2 py-1 text-slate-900 dark:text-white">
                                            </div>
                                            <div class="flex items-center justify-between font-semibold pt-1 border-t border-slate-100 dark:border-slate-800">
                                                <span class="text-slate-700 dark:text-slate-300">4. Bonus Tgl Merah (<span x-text="payForm.holiday_shifts"></span> Shift):</span>
                                                <span class="font-mono text-indigo-600 dark:text-indigo-400 font-bold" x-text="'Rp ' + calculateHolidayBonus().toLocaleString('id-ID')"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tunjangan Aktif -->
                                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-teal-50/60 dark:bg-teal-950/30 border border-teal-200/80 dark:border-teal-800/40 text-xs">
                                        <div class="text-slate-700 dark:text-slate-300 font-semibold flex items-center gap-1.5">
                                            <span>5. Tunjangan Tambahan:</span>
                                            <span class="text-[10px] text-teal-600 dark:text-teal-400 font-normal" x-show="Number(employeeToPay.total_allowance) > 0">(Sesuai daftar tunjangan aktif)</span>
                                        </div>
                                        <span class="font-mono font-bold text-teal-700 dark:text-teal-300" x-text="'Rp ' + (Number(employeeToPay.total_allowance) || 0).toLocaleString('id-ID')"></span>
                                    </div>
                                </div>

                                <!-- Ringkasan THP & Jurnal Akuntansi -->
                                <div class="p-4 rounded-2xl bg-gradient-to-br from-emerald-500/10 to-teal-500/10 dark:from-emerald-950/40 dark:to-teal-950/40 border-2 border-emerald-500/30 dark:border-emerald-500/40 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-xs uppercase font-extrabold tracking-wider text-emerald-900 dark:text-emerald-300">Total Take Home Pay (THP)</span>
                                            <div class="text-[10px] text-slate-500 dark:text-slate-400">Total yang akan diterima karyawan & dibukukan</div>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xl sm:text-2xl font-black font-mono text-emerald-600 dark:text-emerald-400" x-text="'Rp ' + calculatePayTHP().toLocaleString('id-ID')"></span>
                                        </div>
                                    </div>

                                    <div class="pt-2 border-t border-emerald-500/20 flex flex-wrap items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 gap-2">
                                        <div class="flex items-center gap-2">
                                            <span>📋 <strong>Debit:</strong> Akun 5002 - Beban Gaji</span>
                                            <span>•</span>
                                            <span>💳 <strong>Kredit:</strong> <span x-text="employeeToPay.asset_account ? employeeToPay.asset_account.name : 'Kas Operasional'"></span></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Catatan Slip -->
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1">Catatan Slip (Opsional)</label>
                                    <input type="text" name="notes" x-model="payForm.notes" placeholder="Contoh: Termasuk penyesuaian lembur & bonus penjualan" class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500">
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                                    <button type="button" @click="showPayModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">Batal</button>
                                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-bold shadow-md shadow-emerald-950/20 transition-all hover:scale-105 active:scale-95 cursor-pointer">
                                        Simpan & Bukukan Gaji
                                    </button>
                                </div>
                            </form>
                        </template>
                    </div>
                </div>
            </div>

            <!-- MODAL HAPUS KARYAWAN -->
            <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="min-h-screen px-4 text-center flex items-center justify-center">
                    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" @click="showDeleteModal = false"></div>
                    <div class="inline-block w-full max-w-sm p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl relative z-10">
                        <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800 text-rose-600">
                            <div class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-950 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Hapus Data Karyawan</h3>
                        </div>

                        <template x-if="employeeToDelete">
                            <form :action="'{{ url('employees') }}/' + employeeToDelete.id" method="POST" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="_method" value="DELETE">
                                <p class="text-xs text-slate-600 dark:text-slate-300">
                                    Apakah Anda yakin ingin menghapus data karyawan <strong x-text="employeeToDelete.name"></strong>?
                                    Riwayat jurnal penggajian yang sudah dibukukan tidak akan terhapus.
                                </p>
                                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                                    <button type="button" @click="showDeleteModal = false" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Batal</button>
                                    <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-sm">Ya, Hapus Karyawan</button>
                                </div>
                            </form>
                        </template>
                    </div>
                </div>
            </div>

            <!-- MODAL KELOLA TUNJANGAN PEGAWAI -->
            <div x-show="showAllowanceModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="min-h-screen px-4 text-center flex items-center justify-center">
                    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" @click="showAllowanceModal = false"></div>
                    <div class="inline-block w-full max-w-3xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl relative z-10 max-h-[90vh] flex flex-col">
                        
                        <!-- Header Modal -->
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 shrink-0">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-teal-100 dark:bg-teal-950 text-teal-700 dark:text-teal-300 flex items-center justify-center shadow-xs">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white" x-text="isEditAllowance ? 'Edit Jenis Tunjangan' : 'Kelola Tunjangan Pegawai'"></h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Atur jenis alokasi tunjangan per divisi atau per individu karyawan</p>
                                </div>
                            </div>
                            <button type="button" @click="showAllowanceModal = false" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Content Scrollable -->
                        <div class="overflow-y-auto space-y-6 pt-4 pr-1">
                            
                            <!-- Form Tambah / Edit Tunjangan -->
                            <div class="p-4 rounded-2xl bg-slate-50/80 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200" x-text="isEditAllowance ? '✏️ Edit Data Tunjangan' : '➕ Tambah Jenis Tunjangan Baru'"></span>
                                    <button 
                                        type="button" 
                                        x-show="isEditAllowance" 
                                        @click="openAddAllowanceModal()"
                                        class="text-xs text-teal-600 dark:text-teal-400 hover:underline font-semibold"
                                    >
                                        Batal Edit & Tambah Baru
                                    </button>
                                </div>

                                <form 
                                    :action="isEditAllowance ? '{{ url('allowances') }}/' + allowanceForm.id : '{{ route('allowances.store') }}'" 
                                    method="POST" 
                                    class="space-y-4"
                                    @submit="if(allowanceForm.target_type === 'employee' && allowanceForm.target_employee_ids.length === 0) { alert('Silakan pilih minimal satu pegawai penerima tunjangan.'); $event.preventDefault(); }"
                                >
                                    @csrf
                                    <template x-if="isEditAllowance">
                                        <input type="hidden" name="_method" value="PUT">
                                    </template>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                                        <!-- Nama Tunjangan -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Tunjangan</label>
                                            <input 
                                                type="text" 
                                                name="name" 
                                                x-model="allowanceForm.name" 
                                                required 
                                                placeholder="Contoh: Tunjangan Makan, Transport, Jabatan" 
                                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500"
                                            >
                                        </div>

                                        <!-- Nominal Tunjangan -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Besaran Tunjangan (Rp)</label>
                                            <input 
                                                type="number" 
                                                name="amount" 
                                                x-model="allowanceForm.amount" 
                                                required 
                                                min="0" 
                                                step="1000" 
                                                placeholder="100000" 
                                                class="w-full text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500"
                                            >
                                        </div>

                                        <!-- Target Tunjangan -->
                                        <div :class="allowanceForm.target_type === 'role' ? 'md:col-span-1' : 'md:col-span-2'">
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Target Alokasi Penerima</label>
                                            <select 
                                                name="target_type" 
                                                x-model="allowanceForm.target_type" 
                                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500"
                                            >
                                                <option value="all">Semua Karyawan Aktif</option>
                                                <option value="role">Berdasarkan Divisi / Role</option>
                                                <option value="employee">Khusus Pegawai Tertentu (Bisa Multi-Pilih)</option>
                                            </select>
                                        </div>

                                        <!-- Divisi (Role) jika target_type = role -->
                                        <div x-show="allowanceForm.target_type === 'role'">
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pilih Divisi / Role</label>
                                            <select 
                                                name="target_role" 
                                                x-model="allowanceForm.target_role" 
                                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500"
                                            >
                                                @foreach(\App\Models\Employee::ROLES as $roleVal => $roleLabel)
                                                    <option value="{{ $roleVal }}">{{ $roleLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Karyawan Spesifik jika target_type = employee (Bisa 2 atau lebih pegawai) -->
                                        <div x-show="allowanceForm.target_type === 'employee'" class="md:col-span-2 space-y-2">
                                            <div class="flex items-center justify-between">
                                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                                    Pilih Karyawan Penerima (<span class="text-teal-600 dark:text-teal-400 font-bold" x-text="allowanceForm.target_employee_ids.length"></span> dipilih)
                                                </label>
                                                <div class="flex items-center gap-2 text-[11px]">
                                                    <button type="button" @click="selectAllAllowanceEmployees()" class="text-teal-600 dark:text-teal-400 hover:underline font-semibold cursor-pointer">Pilih Semua</button>
                                                    <span class="text-slate-300 dark:text-slate-700">|</span>
                                                    <button type="button" @click="clearAllAllowanceEmployees()" class="text-slate-500 hover:text-rose-500 hover:underline cursor-pointer">Hapus Pilihan</button>
                                                </div>
                                            </div>

                                            <!-- Pencarian nama karyawan cepat -->
                                            <div class="relative">
                                                <input 
                                                    type="text" 
                                                    x-model="allowanceEmployeeSearch" 
                                                    placeholder="Ketik untuk memfilter nama pegawai..." 
                                                    class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-1.5 pl-8 text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500"
                                                >
                                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                            </div>

                                            <!-- Daftar Checkbox Karyawan (Scrollable) -->
                                            <div class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/60 p-2 space-y-1">
                                                @foreach($employees as $empOption)
                                                    <label 
                                                        x-show="!allowanceEmployeeSearch || '{{ strtolower(addslashes($empOption->name)) }}'.includes(allowanceEmployeeSearch.toLowerCase()) || '{{ strtolower(addslashes($empOption->position)) }}'.includes(allowanceEmployeeSearch.toLowerCase())"
                                                        class="flex items-center justify-between p-2 rounded-lg transition-colors cursor-pointer border border-transparent"
                                                        :class="allowanceForm.target_employee_ids.includes({{ $empOption->id }}) ? 'bg-teal-50 dark:bg-teal-950/50 border-teal-200 dark:border-teal-800 text-teal-950 dark:text-teal-200 font-semibold' : 'hover:bg-white dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300'"
                                                    >
                                                        <div class="flex items-center gap-2.5">
                                                            <input 
                                                                type="checkbox" 
                                                                name="target_employee_ids[]" 
                                                                value="{{ $empOption->id }}"
                                                                :checked="allowanceForm.target_employee_ids.includes({{ $empOption->id }})"
                                                                @change="toggleAllowanceEmployee({{ $empOption->id }})"
                                                                class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 border-slate-300 dark:border-slate-600"
                                                            >
                                                            <span class="text-xs">{{ $empOption->name }}</span>
                                                        </div>
                                                        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">
                                                            {{ $empOption->position ?: ($empOption->role_label ?? '') }}
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                                * Anda dapat mencentang 2 atau lebih pegawai yang berhak menerima tunjangan ini.
                                            </p>
                                        </div>

                                        <!-- Catatan -->
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Keterangan / Catatan</label>
                                            <input 
                                                type="text" 
                                                name="notes" 
                                                x-model="allowanceForm.notes" 
                                                placeholder="Catatan tambahan (opsional)" 
                                                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500"
                                            >
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between pt-1">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" name="is_active" value="1" x-model="allowanceForm.is_active" class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500">
                                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Tunjangan Aktif</span>
                                        </label>

                                        <button 
                                            type="submit" 
                                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold shadow-sm transition-all"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span x-text="isEditAllowance ? 'Simpan Perubahan' : 'Tambahkan Tunjangan'"></span>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Daftar Master Tunjangan Yang Ada -->
                            <div class="space-y-3">
                                <h4 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                                    Daftar Jenis Tunjangan Terdaftar ({{ $allowances->count() }})
                                </h4>

                                <div class="overflow-x-auto rounded-2xl border border-slate-200/90 dark:border-slate-700">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-slate-100 dark:bg-slate-800 text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase">
                                                <th class="px-3.5 py-2.5">Nama Tunjangan</th>
                                                <th class="px-3 py-2.5">Target Alokasi</th>
                                                <th class="px-3 py-2.5">Besaran (Rp)</th>
                                                <th class="px-3 py-2.5 text-center">Status</th>
                                                <th class="px-3 py-2.5 text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200/70 dark:divide-slate-800">
                                            @forelse($allowances as $alw)
                                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                                    <td class="px-3.5 py-2.5 font-bold text-slate-900 dark:text-white">
                                                        <div>{{ $alw->name }}</div>
                                                        @if($alw->notes)
                                                            <div class="text-[10px] font-normal text-slate-400">{{ $alw->notes }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2.5">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                            {{ $alw->target_label }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2.5 font-mono font-bold text-teal-700 dark:text-teal-300">
                                                        {{ $alw->formatted_amount }}
                                                    </td>
                                                    <td class="px-3 py-2.5 text-center">
                                                        <form action="{{ route('allowances.toggle', $alw) }}" method="POST" class="inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button 
                                                                type="submit" 
                                                                class="px-2 py-0.5 rounded-full text-[10px] font-bold transition-all cursor-pointer {{ $alw->is_active ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-300/80' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}"
                                                                title="Klik untuk mengubah status aktif/nonaktif"
                                                            >
                                                                {{ $alw->is_active ? '● Aktif' : '○ Nonaktif' }}
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            <button 
                                                                type="button" 
                                                                @click="openEditAllowanceModal({{ Js::from($alw) }})" 
                                                                class="p-1.5 rounded-lg text-slate-600 hover:text-blue-600 bg-slate-100 hover:bg-blue-50 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                                                                title="Edit Tunjangan"
                                                            >
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                            </button>
                                                            <form action="{{ route('allowances.destroy', $alw) }}" method="POST" class="inline" onsubmit="return confirm('Hapus tunjangan {{ $alw->name }}?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button 
                                                                    type="submit" 
                                                                    class="p-1.5 rounded-lg text-slate-600 hover:text-rose-600 bg-slate-100 hover:bg-rose-50 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                                                                    title="Hapus Tunjangan"
                                                                >
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">
                                                        Belum ada jenis tunjangan yang dibuat. Silakan tambahkan melalui form di atas.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>

                        <!-- Footer Modal -->
                        <div class="flex items-center justify-end pt-4 border-t border-slate-100 dark:border-slate-800 shrink-0 mt-4">
                            <button type="button" @click="showAllowanceModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Tutup</button>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
