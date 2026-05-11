<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'module' => $this->module,
            'details' => $this->details,
            'ip' => $this->ip,
            'date_action' => $this->date_action,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'prenom' => $this->user?->prenom,
                'email' => $this->user?->email,
            ]),
        ];
    }
}
