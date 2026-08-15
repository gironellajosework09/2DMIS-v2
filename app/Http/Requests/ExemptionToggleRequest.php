<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * P7 multi-device exemption toggle. `grant` is optional (a checkbox is absent
 * when unchecked); the controller resolves it to false and treats the toggle
 * as idempotent (a no-op writes nothing).
 */
class ExemptionToggleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grant' => ['sometimes', 'boolean'],
        ];
    }
}
