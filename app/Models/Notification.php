<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id', 'type', 'titre',
        'message', 'lien', 'module', 'lu', 'lu_le',
    ];

    protected $casts = [
        'lu'    => 'boolean',
        'lu_le' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}