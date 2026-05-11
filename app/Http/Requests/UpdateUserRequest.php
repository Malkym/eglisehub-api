<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $this->route('user'),
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|in:super_admin,admin_ministere,createur_contenu,moderateur',
            'ministere_id' => 'required_if:role,admin_ministere,createur_contenu,moderateur|nullable|exists:ministeres,id',
            'actif' => 'nullable|boolean',
        ];
    }
}
