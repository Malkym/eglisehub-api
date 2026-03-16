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

    // Helpers pour vérifier le rôle
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdminMinistere(): bool
    {
        return $this->role === 'admin_ministere';
    }
}
