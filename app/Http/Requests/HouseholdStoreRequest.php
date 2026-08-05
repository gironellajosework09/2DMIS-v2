<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HouseholdStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'head_household' => ['required', 'integer', 'exists:tbl_clients,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'head_household.required' => 'Please search for and select a head of household.',
            'head_household.exists' => 'Selected client was not found.',
        ];
    }
}
