<?php

namespace App\Http\Requests;

use App\Enums\WarehouseType;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:main,project',
            'project_id' => 'nullable|required_if:type,project|exists:projects,id',
            'location' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
    
    protected function prepareForValidation()
    {
        // Force project_id to be null if type is main, preventing accidental data pollution
        if ($this->type === 'main') {
            $this->merge([
                'project_id' => null,
            ]);
        }
    }
}
