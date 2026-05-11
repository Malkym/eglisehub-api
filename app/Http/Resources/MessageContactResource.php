<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_expediteur' => $this->nom_expediteur,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'sujet' => $this->sujet,
            'message' => $this->message,
            'statut' => $this->statut,
            'lu_le' => $this->lu_le,
            'reponses' => $this->whenLoaded('reponses', fn () => $this->reponses->map(fn ($reponse) => [
                'id' => $reponse->id,
                'contenu' => $reponse->contenu,
                'user' => $reponse->relationLoaded('user') ? [
                    'id' => $reponse->user?->id,
                    'name' => $reponse->user?->name,
                    'prenom' => $reponse->user?->prenom,
                ] : null,
                'created_at' => $reponse->created_at?->toIso8601String(),
                'updated_at' => $reponse->updated_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
