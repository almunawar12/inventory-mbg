<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'sale_id'              => ['required', 'exists:sales,id'],
            'return_date'          => ['nullable', 'date'],
            'reason'               => ['nullable', 'string', 'max:1000'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
        ];
    }
}
