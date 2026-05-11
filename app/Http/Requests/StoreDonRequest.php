<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subdomain' => 'required|string',
            'nom' => 'required|string|max:255',
            'telephone' => 'required|string|max:20',
            'montant' => 'required|numeric|min:100',
            'type_don' => 'required|in:don,dime,offrande',
            'operateur' => 'required|in:orange,moov,airtel',
        ];
    }
}
