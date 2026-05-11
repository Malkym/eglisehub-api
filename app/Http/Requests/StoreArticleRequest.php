<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => 'required|string|max:255',
            'type_contenu' => 'required|in:texte,lien_externe,video_youtube,audio,mixte',
            'resume' => 'nullable|string|max:500',
            'contenu' => 'nullable|string',
            'image_une' => 'nullable|string',
            'categorie' => 'nullable|string|max:100',
            'url_externe' => 'required_if:type_contenu,lien_externe|nullable|url',
            'youtube_id' => 'required_if:type_contenu,video_youtube|nullable|string|max:20',
            'duree' => 'nullable|string|max:20',
            'auteur_externe' => 'nullable|string|max:255',
            'en_avant' => 'nullable|boolean',
            'commentaires_actifs' => 'nullable|boolean',
            'statut' => 'nullable|in:publie,brouillon',
            'date_publication' => 'nullable|date',
        ];
    }
}
