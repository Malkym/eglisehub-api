<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAction extends Model
{
    protected $table = 'logs_actions';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ministere_id',
        'action',
        'module',
        'details',
        'ip',
        'date_action',
    ];

    protected $casts = [
        'date_action' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}
