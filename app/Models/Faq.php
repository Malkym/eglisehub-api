<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id', 'question', 'reponse',
        'categorie', 'ordre', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}