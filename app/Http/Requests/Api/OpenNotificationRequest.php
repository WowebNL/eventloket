<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OpenNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedHost = parse_url(config('openzaak.url', ''), PHP_URL_HOST);

        $openzaakHostRule = function (string $attribute, mixed $value, \Closure $fail) use ($allowedHost): void {
            if (! $allowedHost || parse_url($value, PHP_URL_HOST) !== $allowedHost) {
                $fail("The {$attribute} must point to the configured OpenZaak host.");
            }
        };

        return [
            'actie' => 'required|string|in:create,update,delete,partial_update',
            'kanaal' => 'required|string|in:zaken,objecten,documenten,besluiten,autorisaties',
            'resource' => 'required|string',
            'hoofdObject' => ['required', 'url', $openzaakHostRule],
            'resourceUrl' => ['required', 'url', $openzaakHostRule],
            'aanmaakdatum' => 'required|date',
        ];
    }
}
