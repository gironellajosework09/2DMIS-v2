<?php

namespace App\Http\Requests;

use App\Services\TransactionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * P7 program-permission save. Every program is validated against the verified
 * 17-program catalog (TransactionService::PROGRAMS, set-identical to the
 * production enum) so no orphan rows reach tbl_program_permissions.
 *
 * The set is nullable because an empty save is a valid v1 full-replace that
 * removes every program grant.
 */
class ProgramPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'programs' => ['nullable', 'array'],
            'programs.*' => ['string', Rule::in(TransactionService::PROGRAMS)],
        ];
    }

    public function messages(): array
    {
        return [
            'programs.*.in' => 'One of the selected programs is not a known program.',
        ];
    }
}
