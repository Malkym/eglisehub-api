<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorshipScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jour' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin' => 'required|date_format:H:i|after:heure_debut',
            'is_highlight' => 'boolean',
            'note' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'ordre' => 'nullable|integer|min:0',
        ];
    }
}
