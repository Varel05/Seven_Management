<?php

namespace App\Enums;

enum EmployeeRole: string
{
    // Di Atas Staff (Manajemen, Pimpinan, dan Pengawasan)
    case Owner = 'owner';
    case Manager = 'manager';
    case Akuntan = 'akuntan';
    case Hrd = 'hrd';
    case Supervisor = 'supervisor';

    // Tingkat Staff (Pelaksana & Operasional)
    case Staff = 'staff';
    case Cs = 'cs';

    /**
     * Label dalam Bahasa Indonesia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner / Pemilik',
            self::Manager => 'Manager / Pengelola',
            self::Akuntan => 'Akuntan / Finance',
            self::Hrd => 'HRD / Personalia',
            self::Supervisor => 'Supervisor / Pengawas',
            self::Staff => 'Staff Umum',
            self::Cs => 'Customer Service (CS)',
        };
    }

    /**
     * Kategori hierarki: 'above_staff' (di atas staff) atau 'staff' (tingkat staff).
     */
    public function level(): string
    {
        return $this->isAboveStaff() ? 'above_staff' : 'staff';
    }

    /**
     * Label hierarki peran.
     */
    public function levelLabel(): string
    {
        return $this->isAboveStaff() ? 'Di Atas Staff (Manajemen)' : 'Tingkat Staff (Pelaksana)';
    }

    /**
     * Apakah peran ini termasuk kelompok di atas staff.
     */
    public function isAboveStaff(): bool
    {
        return match ($this) {
            self::Owner, self::Manager, self::Akuntan, self::Hrd, self::Supervisor => true,
            self::Staff, self::Cs => false,
        };
    }

    /**
     * Apakah peran ini termasuk staf pelaksana operasional.
     */
    public function isStaff(): bool
    {
        return ! $this->isAboveStaff();
    }

    /**
     * Kelas warna badge Tailwind CSS.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Owner => 'bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            self::Manager => 'bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            self::Akuntan => 'bg-blue-100 dark:bg-blue-950/80 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            self::Hrd => 'bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            self::Supervisor => 'bg-teal-100 dark:bg-teal-950/80 text-teal-700 dark:text-teal-300 border-teal-200 dark:border-teal-800',
            self::Cs => 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::Staff => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }

    /**
     * Seluruh nilai string enum.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Seluruh daftar enum di atas staff.
     *
     * @return array<int, self>
     */
    public static function aboveStaff(): array
    {
        return array_filter(self::cases(), fn (self $role) => $role->isAboveStaff());
    }

    /**
     * Seluruh daftar string value peran di atas staff.
     *
     * @return array<int, string>
     */
    public static function aboveStaffValues(): array
    {
        return array_values(array_map(fn (self $role) => $role->value, self::aboveStaff()));
    }

    /**
     * Seluruh daftar enum staf pelaksana.
     *
     * @return array<int, self>
     */
    public static function staff(): array
    {
        return array_filter(self::cases(), fn (self $role) => $role->isStaff());
    }

    /**
     * Seluruh daftar string value peran staf pelaksana.
     *
     * @return array<int, string>
     */
    public static function staffValues(): array
    {
        return array_values(array_map(fn (self $role) => $role->value, self::staff()));
    }

    /**
     * Daftar opsi terkelompok (Grouped Options) untuk form select HTML.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        return [
            'Di Atas Staff (Manajemen & Pimpinan)' => [
                self::Owner->value => self::Owner->label(),
                self::Manager->value => self::Manager->label(),
                self::Akuntan->value => self::Akuntan->label(),
                self::Hrd->value => self::Hrd->label(),
                self::Supervisor->value => self::Supervisor->label(),
            ],
            'Tingkat Staff (Pelaksana & Operasional)' => [
                self::Staff->value => self::Staff->label(),
                self::Cs->value => self::Cs->label(),
            ],
        ];
    }
}
