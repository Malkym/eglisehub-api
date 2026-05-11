<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_original' => $this->nom_original,
            'url' => $this->chemin ? \Storage::url($this->chemin) : null,
            'type' => $this->type,
            'mime_type' => $this->mime_type,
            'taille' => $this->formatTaille(),
            'categorie' => $this->categorie,
            'alt_text' => $this->alt_text,
            'visible' => $this->visible,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function formatTaille(): string
    {
        $bytes = $this->taille ?? 0;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
