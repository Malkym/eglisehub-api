<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => 'required|string|max:255',
            'contenu' => 'nullable|string',
            'image_hero' => 'nullable|string',
            'dans_menu' => 'boolean',
            'ordre_menu' => 'integer',
            'statut' => 'in:publie,brouillon',
            'meta_titre' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ];
    }
}
