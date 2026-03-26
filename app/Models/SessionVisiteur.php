<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionVisiteur extends Model
{
    protected $table = 'sessions_visiteurs';

    protected $fillable = [
        'ministere_id', 'session_id', 'page_visitee',
        'ip', 'pays', 'ville', 'navigateur',
        'appareil', 'os', 'referrer', 'duree_secondes',
    ];

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}