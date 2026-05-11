<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSliderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => 'required|string|max:255',
            'sous_titre' => 'nullable|string|max:500',
            'type_media' => 'in:image,video',
            'image' => 'required_if:type_media,image|nullable|file|image|max:5120',
            'video' => 'required_if:type_media,video|nullable|file|mimes:mp4,mov,avi,webm,mkv|max:20480',
            'bouton_texte' => 'nullable|string|max:50',
            'bouton_lien' => 'nullable|string|max:255',
            'position_texte' => 'in:gauche,centre,droite',
            'couleur_texte' => 'nullable|string|max:7',
            'couleur_fond' => 'nullable|string|max:7',
            'ordre' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
