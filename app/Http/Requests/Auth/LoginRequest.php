<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Tout visiteur est autorisé à tenter de se connecter
    }

    public function rules(): array
    {
        return [
            // L'identifiant peut être un email ou un matricule
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => "L'identifiant ou matricule est obligatoire.",
            'password.required' => "Le mot de passe est obligatoire.",
        ];
    }
}
