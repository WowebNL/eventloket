<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LocationServerCheckRequest extends FormRequest
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
            'polygons' => 'required_without_all:addresses,address,line,lines|nullable|json',
            'lines' => 'required_without_all:addresses,address,line,polygons|nullable|json',
            'line' => 'required_without_all:addresses,address,polygons,lines|nullable|json',
            'addresses' => 'required_without_all:address,line,polygons,lines|nullable|json',
            'address' => 'required_without_all:addresses,line,polygons,lines|nullable|json',
        ];
    }
}
