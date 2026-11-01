<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CareerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^[+\d][\d\s\-()]{6,19}$/'],
            'role' => ['required', 'string', 'max:120'],
            'years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'licence' => ['nullable', 'string', 'max:60'],
            'about' => ['nullable', 'string', 'max:2000'],
            'website' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter your full name.',
            'phone.required' => 'Enter a phone number we can call you on.',
            'phone.regex' => 'Enter a phone number like 0816 216 3235.',
            'role.required' => 'Choose the role you are applying for.',
        ];
    }
}
