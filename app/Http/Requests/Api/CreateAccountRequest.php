<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|max:72',
            'plan_id'  => ['required', 'integer', 'exists:plans,id'],
            'domain'   => 'nullable|string|max:253|regex:/^[a-zA-Z0-9\-\.]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'  => 'Ya existe una cuenta con este email.',
            'password.min'  => 'La contraseña debe tener al menos 8 caracteres.',
            'plan_id.exists'=> 'El plan seleccionado no existe.',
        ];
    }
}