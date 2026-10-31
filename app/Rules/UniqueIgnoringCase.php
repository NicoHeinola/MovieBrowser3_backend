<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueIgnoringCase implements ValidationRule
{
    public function __construct(
        private string $table,
        private string $column,
        private ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Non-string values are reported by the `string` rule.
        if (!is_string($value)) {
            return;
        }

        $taken = DB::table($this->table)
            ->whereRaw("lower({$this->column}) = ?", [mb_strtolower($value)])
            ->when($this->ignoreId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($taken) {
            $fail('The :attribute has already been taken.');
        }
    }
}
