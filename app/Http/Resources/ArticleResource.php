<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'slug' => $this->slug,
            'resume' => $this->resume,
            'contenu' => $this->contenu,
            'type_contenu' => $this->type_contenu,
            'image_une' => $this->image_une,
            'image_une_url' => $this->image_une ? \Storage::url($this->image_une) : null,
            'categorie' => $this->categorie,
            'url_externe' => $this->url_externe,
            'youtube_id' => $this->youtube_id,
            'duree' => $this->duree,
            'auteur_externe' => $this->auteur_externe,
            'en_avant' => $this->en_avant,
            'commentaires_actifs' => $this->commentaires_actifs,
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
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'nom' => $tag->nom,
                'slug' => $tag->slug,
                'couleur' => $tag->couleur,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
