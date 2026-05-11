<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'role' => $this->role,
            'ministere_id' => $this->ministere_id,
            'actif' => $this->actif,
            'dernier_login' => $this->dernier_login,
            'ministere' => $this->whenLoaded('ministere', fn () => [
                'id' => $this->ministere?->id,
                'nom' => $this->ministere?->nom,
                'sous_domaine' => $this->ministere?->sous_domaine,
            ]),
        ];
    }
}
