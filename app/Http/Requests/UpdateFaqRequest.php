<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => 'sometimes|string|max:500',
            'reponse' => 'sometimes|string',
            'categorie' => 'nullable|string|max:100',
            'ordre' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
