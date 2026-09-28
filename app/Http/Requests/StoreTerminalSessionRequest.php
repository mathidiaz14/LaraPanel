<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTerminalSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type'      => ['required', Rule::in(['local', 'ssh'])],
            'server_id' => ['required_if:type,ssh', 'nullable', 'integer'],
            'cwd'       => ['nullable', 'string', 'max:255'],
        ];
    }
}