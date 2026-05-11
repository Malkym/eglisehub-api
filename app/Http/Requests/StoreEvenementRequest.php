<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvenementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type_media' => 'nullable|in:image,video',
            'image' => 'nullable|file|image|max:5120',
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm,mkv|max:20480',
            'type' => 'required|in:culte,conference,seminaire,autre',
            'categorie' => 'required|in:ponctuel,recurrent,permanent,saison',
            'mode' => 'required|in:presentiel,en_ligne,hybride',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'date_fin_recurrence' => 'nullable|date|after_or_equal:date_debut',
            'heure_debut' => 'nullable|date_format:H:i',
            'heure_fin' => 'nullable|date_format:H:i|after:heure_debut',
            'frequence' => 'nullable|in:quotidien,hebdomadaire,bimensuel,mensuel,annuel',
            'jours_semaine' => 'nullable|array',
            'jours_semaine.*' => 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche',
            'lieu' => 'nullable|string|max:255',
            'adresse_lieu' => 'nullable|string',
            'lien_streaming' => 'nullable|url',
            'capacite_max' => 'nullable|integer|min:1',
            'inscription_requise' => 'nullable|boolean',
            'est_gratuit' => 'nullable|boolean',
            'prix' => 'nullable|numeric|min:0',
            'devise' => 'nullable|string|max:10',
            'statut' => 'nullable|in:a_venir,en_cours,termine,annule',
            'intervenant' => 'nullable|string|max:255',
            'theme' => 'nullable|string|max:255',
        ];
    }
}
