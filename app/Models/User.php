<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'pin'])]
#[Hidden(['password', 'pin', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
        ];
    }

    public function isOwner(): bool
    {
        return ($this->role ?? 'owner') === 'owner';
    }

    public function isAkuntan(): bool
    {
        return ($this->role ?? '') === 'akuntan';
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role ?? 'owner') {
            'owner' => 'Owner / Pemilik',
            'akuntan' => 'Akuntan / Finance',
            default => ucfirst((string) $this->role),
        };
    }
}
