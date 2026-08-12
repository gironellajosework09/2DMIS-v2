<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScholarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:tbl_clients,id'],
            'program' => ['required', Rule::in(['CEDSSG', 'CEAP', 'CEDSSG_NEW', 'CEAP_NEW', 'OTEA', 'OTCES'])],
            'school' => ['nullable', 'string', 'max:255'],
            'school_type' => ['nullable', 'string', 'max:255'],
            'campus' => ['nullable', 'string', 'max:255'],
            'college_department' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'year_level' => ['nullable', 'string', 'max:50'],
            'is_regular' => ['nullable', 'boolean'],
            'year_start' => ['nullable', 'string'],
            'year_end' => ['nullable', 'string'],
            'landbank_no' => ['nullable', 'string', 'max:255'],
        ];
    }
}
