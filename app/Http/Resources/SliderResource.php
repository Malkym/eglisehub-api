<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SliderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'sous_titre' => $this->sous_titre,
            'type_media' => $this->type_media,
            'url_image' => $this->image ? \Storage::url($this->image) : null,
            'video_url' => $this->video ? \Storage::url($this->video) : null,
            'video_thumbnail_url' => $this->video_thumbnail ? \Storage::url($this->video_thumbnail) : null,
            'bouton_texte' => $this->bouton_texte,
            'bouton_lien' => $this->bouton_lien,
            'position_texte' => $this->position_texte,
            'couleur_texte' => $this->couleur_texte,
            'couleur_fond' => $this->couleur_fond,
            'ordre' => $this->ordre,
            'actif' => $this->actif,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
