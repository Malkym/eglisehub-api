<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'ministere_id', 'nom', 'slug', 'description', 'est_systeme',
    ];

    protected $casts = [
        'est_systeme' => 'boolean',
    ];

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
}