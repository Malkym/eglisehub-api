<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleLightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'slug' => $this->slug,
            'resume' => $this->resume,
            'type_contenu' => $this->type_contenu,
            'image_une_url' => $this->image_une ? \Storage::url($this->image_une) : null,
            'categorie' => $this->categorie,
            'en_avant' => $this->en_avant,
            'statut' => $this->statut,
            'date_publication' => $this->date_publication,
            'vues' => $this->vues,
            'average_rating' => $this->average_rating,
            'rating_count' => $this->rating_count,
            'auteur' => $this->whenLoaded('auteur', fn () => [
                'id' => $this->auteur?->id,
                'name' => $this->auteur?->name,
                'prenom' => $this->auteur?->prenom,
            ]),
        ];
    }
}
