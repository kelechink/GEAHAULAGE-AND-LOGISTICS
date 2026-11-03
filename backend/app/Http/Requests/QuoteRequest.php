<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
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
            'company' => ['nullable', 'string', 'max:160'],
            'phone' => ['required', 'string', 'regex:/^[+\d][\d\s\-()]{6,19}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'from' => ['required', 'string', 'max:160'],
            'to' => ['required', 'string', 'max:160'],
            'service' => ['nullable', 'string', 'max:160'],
            'details' => ['nullable', 'string', 'max:2000'],
            // NDPA 2023: marketing only with a clear opt-in (unticked by default).
            'consent_marketing' => ['nullable', 'boolean'],
            // Honeypot: real visitors never see or fill this field.
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
            'email.email' => 'Enter an email address like name@company.com, or leave it blank.',
            'from.required' => 'Enter the pickup location.',
            'to.required' => 'Enter the delivery location.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['from' => 'pickup location', 'to' => 'delivery location'];
    }
}
