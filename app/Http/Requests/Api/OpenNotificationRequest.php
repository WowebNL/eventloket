<?php

namespace App\Http\Requests\Api;

use App\Services\Zgw\ZgwConnectionResolver;
use Closure;
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
        return [
            // The Notificaties API standard uses "destroy" for deletions; "delete"
            // is kept for tolerance towards non-standard senders.
            'actie' => 'required|string|in:create,update,delete,partial_update,destroy',
            'kanaal' => 'required|string|in:zaken,objecten,documenten,besluiten,autorisaties,zaaktypen',
            'resource' => 'required|string',
            'hoofdObject' => ['required', 'url', $this->zgwHostRule()],
            'resourceUrl' => ['required', 'url', $this->zgwHostRule()],
            'aanmaakdatum' => 'required|date',
            // ZGW notifications may carry kenmerken; we do not act on them, so the
            // field stays optional.
            'kenmerken' => 'sometimes|array',
        ];
    }

    /**
     * SSRF guard: the URL must point at a host belonging to a trusted ZGW
     * connection (any per-municipality connection or main), since the app fetches
     * these URLs.
     */
    private function zgwHostRule(): Closure
    {
        $allowedHosts = app(ZgwConnectionResolver::class)->allowedNotificationHosts();

        return function (string $attribute, mixed $value, Closure $fail) use ($allowedHosts): void {
            $host = is_string($value) ? parse_url($value, PHP_URL_HOST) : null;

            if (! is_string($host) || ! in_array(strtolower($host), $allowedHosts, true)) {
                $fail("The {$attribute} must point to a configured ZGW host.");
            }
        };
    }
}
