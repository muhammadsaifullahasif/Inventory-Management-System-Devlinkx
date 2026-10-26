<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /**
     * Whether this user holds every one of the given permissions. Used to stop
     * users handing out (or taking over accounts with) access they don't have.
     */
    public function holdsAllPermissions(iterable $permissionNames): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return collect($permissionNames)
            ->diff($this->getAllPermissions()->pluck('name'))
            ->isEmpty();
    }

    /**
     * Whether this user may edit, reset the password of, or delete $target.
     */
    public function canManageUser(User $target): bool
    {
        if ($target->isSuperadmin()) {
            return $this->isSuperadmin();
        }

        return $this->holdsAllPermissions($target->getAllPermissions()->pluck('name'));
    }

    /**
     * Whether this user may assign or edit the given role.
     */
    public function canManageRole(Role $role): bool
    {
        if ($role->name === 'superadmin') {
            return $this->isSuperadmin();
        }

        return $this->holdsAllPermissions($role->permissions->pluck('name'));
    }
}
