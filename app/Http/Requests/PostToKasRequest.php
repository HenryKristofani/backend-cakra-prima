<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\StockUsage;

class PostToKasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Determine if the target project is isolated so we can make account_id optional
        $usageId = $this->route('id');
        $isIsolated = false;

        if ($usageId) {
            $usage = StockUsage::with('warehouse.project')->find($usageId);
            $isIsolated = $usage?->warehouse?->project?->is_isolated_cash ?? false;
        }

        return [
            'payment_method' => 'required|in:cash,rek',
            'date'           => 'nullable|date',
            // account_id is required only for non-isolated projects
            'account_id'     => $isIsolated
                ? 'nullable|exists:accounts,id'
                : 'required|exists:accounts,id',
        ];
    }
}
