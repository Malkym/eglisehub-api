<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageReponse extends Model
{
    protected $table = 'messages_reponses';

    protected $fillable = ['message_id', 'user_id', 'contenu'];

    public function message()
    {
        return $this->belongsTo(MessageContact::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}