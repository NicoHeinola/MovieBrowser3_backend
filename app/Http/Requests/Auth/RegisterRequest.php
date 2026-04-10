<?php

namespace App\Http\Requests\Auth;

use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Password|Unique>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class, 'username')],
            'password' => ['required', 'confirmed:password'],
        ];
    }
}
