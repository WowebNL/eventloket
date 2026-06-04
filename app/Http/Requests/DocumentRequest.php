<?php

namespace App\Http\Requests;

use App\Models\Zaak;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $zaak = $this->route('zaak');

        return $zaak instanceof Zaak && $this->user()?->can('view', $zaak) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'nullable|in:view,download',
            'version' => 'nullable|integer|min:1',
        ];
    }
}
