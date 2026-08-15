<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * P7 user creation — v1 register.php / add_user.php port. No server-side
 * strength rule (v1 parity): any non-empty password is accepted, only the
 * confirmation match and the username uniqueness are enforced.
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
            'password' => ['required', 'string', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Please fill in all fields.',
            'username.unique' => 'Username already taken.',
            'password.required' => 'Please fill in all fields.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}
