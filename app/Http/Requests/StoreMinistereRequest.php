<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMinistereRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'type' => 'required|in:eglise,ministere,organisation,para_ecclesial,mission',
            'sous_domaine' => 'required|string|max:100|unique:ministeres,sous_domaine|alpha_dash',
            'description' => 'nullable|string',
            'couleur_primaire' => 'nullable|string|max:7',
            'couleur_secondaire' => 'nullable|string|max:7',
            'email_contact' => 'nullable|email',
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string',
            'ville' => 'nullable|string|max:100',
            'pays' => 'nullable|string|max:100',
            'facebook_url' => 'nullable|url',
            'youtube_url' => 'nullable|url',
            'whatsapp' => 'nullable|string|max:20',
        ];
    }
}
