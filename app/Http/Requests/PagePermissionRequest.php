<?php

namespace App\Http\Requests;

use App\Http\Controllers\AdminPermissionController;
use App\Models\Permission;
use App\Services\AccessControlService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * P7 page-permission save. The page set is validated against the real catalog
 * (distinct tbl_permissions.page_name values + the five P7 keys) so admins can
 * only write rows the gate system understands. The '*' super-admin row is NOT
 * part of the page list — it is the separate, confirmed `super_admin` toggle.
 *
 * The set is nullable because an empty save is a valid v1 full-replace that
 * removes every page grant.
 */
class PagePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $catalog = Permission::query()
            ->distinct()
            ->pluck('page_name')
            ->merge(AdminPermissionController::P7_KEYS)
            ->reject(fn (string $page) => $page === AccessControlService::SUPER_ADMIN_PAGE)
            ->values()
            ->all();

        return [
            'pages' => ['nullable', 'array'],
            'pages.*' => ['string', Rule::in($catalog)],
            'super_admin' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'pages.*.in' => 'One of the selected pages is not a known page key.',
        ];
    }
}
