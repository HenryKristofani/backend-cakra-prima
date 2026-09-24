<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Project;

class StoreFundMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type', \App\Enums\FundMovementType::InitialAllocation->value);
        
        $rules = [
            'type'                   => 'required|string|in:' . implode(',', [
                                            \App\Enums\FundMovementType::InitialAllocation->value,
                                            \App\Enums\FundMovementType::Transfer->value,
                                            \App\Enums\FundMovementType::Withdrawal->value
                                        ]),
            'fund_source_id'         => 'required|exists:fund_sources,id',
            'amount'                 => 'required|numeric|gt:0',
            'payment_method'         => 'required|string',
            'date'                   => 'nullable|date',
            'notes'                  => 'nullable|string',
        ];

        if ($type === \App\Enums\FundMovementType::InitialAllocation->value) {
            $rules['destination_project_id'] = 'required|exists:projects,id';
            $isDestIsolated = Project::where('id', $this->input('destination_project_id'))->value('is_isolated_cash') ?? false;
            $rules['account_id'] = $isDestIsolated ? 'nullable|exists:accounts,id' : 'required|exists:accounts,id';
        } elseif ($type === \App\Enums\FundMovementType::Transfer->value) {
            $rules['destination_project_id'] = 'required|exists:projects,id';
            $rules['source_project_id'] = 'required|exists:projects,id|different:destination_project_id';
            
            $isDestIsolated = Project::where('id', $this->input('destination_project_id'))->value('is_isolated_cash') ?? false;
            $isSourceIsolated = Project::where('id', $this->input('source_project_id'))->value('is_isolated_cash') ?? false;
            
            $rules['source_account_id'] = $isSourceIsolated ? 'nullable|exists:accounts,id' : 'required|exists:accounts,id';
            $rules['destination_account_id'] = $isDestIsolated ? 'nullable|exists:accounts,id' : 'required|exists:accounts,id';
        } elseif ($type === \App\Enums\FundMovementType::Withdrawal->value) {
            $rules['source_project_id'] = 'required|exists:projects,id';
            
            $isSourceIsolated = Project::where('id', $this->input('source_project_id'))->value('is_isolated_cash') ?? false;
            $rules['source_account_id'] = $isSourceIsolated ? 'nullable|exists:accounts,id' : 'required|exists:accounts,id';
        }

        return $rules;
    }
}
