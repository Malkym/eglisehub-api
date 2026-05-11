<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvenementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'description' => $this->description,
            'image_url' => $this->image ? \Storage::url($this->image) : null,
            'type' => $this->type,
            'categorie' => $this->categorie,
            'mode' => $this->mode,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'date_fin_recurrence' => $this->date_fin_recurrence,
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'frequence' => $this->frequence,
            'jours_semaine' => $this->jours_semaine,
            'lieu' => $this->lieu,
            'adresse_lieu' => $this->adresse_lieu,
            'lien_streaming' => $this->lien_streaming,
            'capacite_max' => $this->capacite_max,
            'inscription_requise' => $this->inscription_requise,
            'est_gratuit' => $this->est_gratuit,
            'prix' => $this->prix,
            'devise' => $this->devise,
            'statut' => $this->statut,
            'intervenant' => $this->intervenant,
            'theme' => $this->theme,
            'type_media' => $this->type_media,
            'video_url' => $this->video_url,
            'video_thumbnail_url' => $this->video_thumbnail_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
