<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom_auteur' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'contenu' => 'required|string|min:2|max:1000',
            'parent_id' => 'nullable|integer|exists:article_commentaires,id',
        ];
    }
}
