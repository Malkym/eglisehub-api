<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['ministere_id', 'cle', 'valeur'];

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}
