<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MinistereResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'type' => $this->type,
            'slug' => $this->slug,
            'sous_domaine' => $this->sous_domaine,
            'description' => $this->description,
            'logo_url' => $this->logo ? \Storage::url($this->logo) : null,
            'couleur_primaire' => $this->couleur_primaire,
            'couleur_secondaire' => $this->couleur_secondaire,
            'email_contact' => $this->email_contact,
            'telephone' => $this->telephone,
            'adresse' => $this->adresse,
            'ville' => $this->ville,
            'pays' => $this->pays,
            'facebook_url' => $this->facebook_url,
            'youtube_url' => $this->youtube_url,
            'whatsapp' => $this->whatsapp,
            'statut' => $this->statut,
            'pages_count' => $this->whenCounted('pages'),
            'articles_count' => $this->whenCounted('articles'),
            'evenements_count' => $this->whenCounted('evenements'),
            'utilisateurs_count' => $this->whenCounted('utilisateurs'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
