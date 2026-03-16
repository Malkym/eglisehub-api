<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageContact extends Model
{
    use HasFactory;

    protected $table = 'messages_contact';

    protected $fillable = [
        'ministere_id',
        'nom_expediteur',
        'email',
        'telephone',
        'sujet',
        'message',
        'statut',
        'lu_le',
    ];

    protected $casts = [
        'lu_le' => 'datetime',
    ];

    public function reponses()
    {
        return $this->hasMany(MessageReponse::class, 'message_id');
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}
