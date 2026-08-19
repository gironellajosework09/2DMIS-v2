<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * P12 action-permission save. Each checked box posts a composite
 * "page_name:action" key (e.g. "clients.php:create"), validated against the
 * exact per-page catalogs in config/authorization.php so only rows the gate
 * system understands reach tbl_action_permissions.
 *
 * The set is nullable because an empty save is a valid v1 full-replace that
 * removes every action grant. VIEW is never selectable here — the page row IS
 * the VIEW grant (P9 §6), so a VIEW row would be a redundant duplicate.
 */
class ActionPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $catalog = collect(config('authorization.pages', []))
            ->flatMap(fn (array $page, string $pageName) => collect($page['actions'] ?? [])
                ->reject(fn (string $action) => $action === 'VIEW')
                ->map(fn (string $action) => $pageName.':'.$action))
            ->values()
            ->all();

        return [
            'actions' => ['nullable', 'array'],
            'actions.*' => ['string', Rule::in($catalog)],
        ];
    }

    public function messages(): array
    {
        return [
            'actions.*.in' => 'One of the selected actions is not a known action for any adopted page.',
        ];
    }
}
