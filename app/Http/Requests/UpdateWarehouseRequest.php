<?php

namespace App\Http\Requests;

use App\Enums\WarehouseType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:main,project',
            'project_id' => 'nullable|required_if:type,project|exists:projects,id',
            'location' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
    
    protected function prepareForValidation()
    {
        if ($this->has('type') && $this->type === 'main') {
            $this->merge([
                'project_id' => null,
            ]);
        }
    }
}
