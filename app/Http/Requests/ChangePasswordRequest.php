<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ancien_mot_de_passe' => 'required|string',
            'nouveau_mot_de_passe' => 'required|string|min:8|confirmed',
        ];
    }
}
