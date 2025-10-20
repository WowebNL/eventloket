<?php

namespace App\Http\Requests\Api;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actie' => 'required|string|in:create,update,delete,partial_update',
            'kanaal' => 'required|string|in:zaken,objecten,documenten',
            'resource' => 'required|string|in:zaak,status,zaakobject,zaakeigenschap,enkelvoudiginformatieobject',
            'hoofdObject' => 'required|url',
            'resourceUrl' => 'required|url',
            'aanmaakdatum' => 'required|date',
        ];
    }
}
