<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Don extends Model
{
    use HasFactory;

    protected $fillable = [
        'ministere_id',
        'nom_donateur',
        'email_donateur',
        'telephone',
        'montant',
        'type_don',
        'operateur',
        'reference_paiement',
        'statut',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }
}
