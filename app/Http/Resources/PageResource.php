<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'slug' => $this->slug,
            'contenu' => $this->contenu,
            'image_hero' => $this->image_hero,
            'dans_menu' => $this->dans_menu,
            'ordre_menu' => $this->ordre_menu,
            'statut' => $this->statut,
            'meta_titre' => $this->meta_titre,
            'meta_description' => $this->meta_description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
