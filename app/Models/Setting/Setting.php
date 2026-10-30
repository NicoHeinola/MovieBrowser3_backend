<?php

namespace App\Models\Setting;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'type'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    /**
     * Get the value attribute, cast based on the type.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => match ($this->type) {
                'json' => json_decode((string) $value, true),
                'number' => is_numeric($value) ? (str_contains((string) $value, '.') ? (float) $value : (int) $value) : $value,
                default => $value,
            },
            set: fn (mixed $value) => match ($this->type) {
                'json' => json_encode($value),
                default => (string) $value,
            }
        );
    }
}
