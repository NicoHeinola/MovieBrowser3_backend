<?php

namespace App\Http\Requests\Auth;

use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', new UniqueIgnoringCase('users', 'username')],
            'password' => ['required', 'confirmed'],
        ];
    }
}
