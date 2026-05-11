<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_donateur' => $this->nom_donateur,
            'email_donateur' => $this->email_donateur,
            'telephone' => $this->telephone,
            'montant' => $this->montant,
            'type_don' => $this->type_don,
            'operateur' => $this->operateur,
            'reference_paiement' => $this->reference_paiement,
            'statut' => $this->statut,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
