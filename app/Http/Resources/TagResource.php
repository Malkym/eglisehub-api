<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'slug' => $this->slug,
            'couleur' => $this->couleur,
            'articles_count' => $this->whenCounted('articles'),
            'pages_count' => $this->whenCounted('pages'),
        ];
    }
}
