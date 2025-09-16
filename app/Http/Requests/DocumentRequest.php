<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class DocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // TODO refactor this later
        return auth()->check() && in_array(auth()->user()->role, [Role::Reviewer, Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Organiser]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'nullable|in:view,download',
        ];
    }
}
