<?php

namespace App\Http\Requests\User;

use App\Models\User\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Unique>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class, 'username')],
            'password' => ['required', 'confirmed'],
            'is_admin' => ['sometimes', 'boolean'],
        ];
    }
}
