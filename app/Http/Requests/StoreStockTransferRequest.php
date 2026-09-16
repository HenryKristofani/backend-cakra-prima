<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\StockTransferType;
use App\Enums\StockTransferSourceType;
use Illuminate\Validation\Rules\Enum;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'string', new Enum(StockTransferType::class), 'in:in,transfer,usage'],
            'notes' => 'nullable|string',
            
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            // unit_price is only required for receipts (type=in); transfers look it up from history
            'items.*.unit_price' => 'nullable|numeric|gte:0',
        ];

        if ($this->input('type') === 'in') {
            $rules['destination_warehouse_id'] = 'required|exists:warehouses,id';
            $rules['source_type'] = ['required', 'string', new Enum(StockTransferSourceType::class)];
            // For receipts, price must be explicitly provided
            $rules['items.*.unit_price'] = 'required|numeric|gte:0';
        } elseif ($this->input('type') === 'transfer') {
            $rules['source_warehouse_id'] = 'required|exists:warehouses,id';
            $rules['destination_warehouse_id'] = 'required|exists:warehouses,id|different:source_warehouse_id';
        } elseif ($this->input('type') === 'usage') {
            $rules['warehouse_id'] = 'required|exists:warehouses,id';
            $rules['items.*.usage_note'] = 'nullable|string';
        }

        return $rules;
    }
}
