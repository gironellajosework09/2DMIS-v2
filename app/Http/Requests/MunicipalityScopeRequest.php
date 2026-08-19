<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * P12 municipality-scope save. Explicit municipality ids must be real
 * tbl_municipalities ids; `all` is the separate, confirmed ALL toggle that
 * writes the reserved marker (0). The marker is never selectable from the
 * municipality list — it is its own switch, mirroring how the '*' page row
 * has a dedicated toggle.
 *
 * The set is nullable because an empty save is a valid full-replace that
 * removes every scope row (fail-closed: the user then matches no records).
 */
class MunicipalityScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'municipalities' => ['nullable', 'array'],
            'municipalities.*' => ['integer', Rule::exists('tbl_municipalities', 'id')],
            'all' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'municipalities.*.exists' => 'One of the selected municipalities is not a known municipality.',
        ];
    }
}
