<?php

namespace App\Http\Requests\User;

use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', new UniqueIgnoringCase('users', 'username')],
            'password' => ['required', 'confirmed'],
            'is_admin' => ['sometimes', 'boolean'],
        ];
    }
}
