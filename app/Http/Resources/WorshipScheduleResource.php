<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorshipScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jour' => $this->jour,
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'is_highlight' => $this->is_highlight,
            'note' => $this->note,
            'is_active' => $this->is_active,
            'ordre' => $this->ordre,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
