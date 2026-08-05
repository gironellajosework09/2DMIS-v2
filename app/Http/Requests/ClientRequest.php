<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lastname' => ['required', 'string', 'max:100'],
            'firstname' => ['required', 'string', 'max:100'],
            'middlename' => ['nullable', 'string', 'max:100'],
            'extensionname' => ['nullable', 'string', 'max:20'],
            'city_municipality' => ['required', 'integer', 'exists:tbl_municipalities,id'],
            'barangay' => ['required', 'integer', 'exists:tbl_barangays,id'],
            'house_no' => ['nullable', 'string', 'max:50'],
            'household_id' => ['nullable', 'integer', 'exists:tbl_household,id'],
            'mobile_no' => ['nullable', 'string', 'max:15'],
            'email' => ['nullable', 'email', 'max:255'],
            'birthdate' => ['required', 'date', 'before:today'],
            'sex' => ['required', 'in:MALE,FEMALE'],
            'civil_status' => ['required', 'in:SINGLE,MARRIED,WIDOWED'],
            'pwd' => ['required', 'in:YES,NO'],
            'ip' => ['required', 'in:YES,NO'],
            'ip_group' => ['nullable', 'required_if:ip,YES', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'precinct_no' => ['nullable', 'string', 'max:50'],
            'voter_id' => ['nullable', 'string', 'max:50'],
            'aff_org' => ['nullable', 'array', 'max:5'],
            'aff_org.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
