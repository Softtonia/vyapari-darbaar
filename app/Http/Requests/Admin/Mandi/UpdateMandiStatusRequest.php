<?php

namespace App\Http\Requests\Admin\Mandi;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMandiStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'boolean'],
        ];
    }
}
