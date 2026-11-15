<?php

namespace App\Http\Requests\User;

use App\Models\User\User;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User|null $subject */
        $subject = $this->route('user');

        return [
            'username' => ['sometimes', 'required', 'string', 'max:255', new UniqueIgnoringCase('users', 'username', $subject?->id)],
            'password' => ['sometimes', 'required', 'confirmed'],
            'is_admin' => ['sometimes', Rule::prohibitedIf(!($this->user()?->is_admin ?? false)), 'boolean'],
        ];
    }
}
