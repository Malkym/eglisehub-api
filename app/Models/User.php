<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'prenom',
        'email',
        'password',
        'role',
        'ministere_id',
        'actif',
        'dernier_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'dernier_login'     => 'datetime',
        'actif'             => 'boolean',
        'password'          => 'hashed',
    ];

    // Appartient à un ministère
    public function ministere()
    {
        return $this->belongsTo(Ministere::class, 'ministere_id');
    }

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN_MINISTERE = 'admin_ministere';
    public const ROLE_CREATEUR_CONTENU = 'createur_contenu';
    public const ROLE_MODERATEUR = 'moderateur';

    public static array $ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN_MINISTERE,
        self::ROLE_CREATEUR_CONTENU,
        self::ROLE_MODERATEUR,
    ];

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdminMinistere(): bool
    {
        return $this->role === self::ROLE_ADMIN_MINISTERE;
    }

    public function isCreateurContenu(): bool
    {
        return $this->role === self::ROLE_CREATEUR_CONTENU;
    }

    public function isModerateur(): bool
    {
        return $this->role === self::ROLE_MODERATEUR;
    }

    public function canManageContent(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN_MINISTERE,
            self::ROLE_CREATEUR_CONTENU,
        ]);
    }

    public function canModerate(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN_MINISTERE,
            self::ROLE_MODERATEUR,
        ]);
    }

    public function canManageUsers(): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN_MINISTERE,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('actif', true);
    }

    public function scopeOfMinistere($query, int $ministereId)
    {
        return $query->where('ministere_id', $ministereId);
    }
}
