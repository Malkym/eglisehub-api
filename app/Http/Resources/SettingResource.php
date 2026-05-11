<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $decoded = json_decode($this->valeur, true);
        return [
            'cle' => $this->cle,
            'valeur' => $decoded !== null && json_last_error() === JSON_ERROR_NONE ? $decoded : $this->valeur,
        ];
    }
}
