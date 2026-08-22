<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * P7 user creation — v1 register.php / add_user.php port. The minimum-8
 * rule mirrors the v1 administrator password-reset rule (manage_php.php):
 * at least 8 characters plus the confirmation match; the username
 * uniqueness is enforced as well.
 */
class UserCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100', 'unique:tbl_users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Please fill in all fields.',
            'username.unique' => 'Username already taken.',
            'password.required' => 'Please fill in all fields.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}
