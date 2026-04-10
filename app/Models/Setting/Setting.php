<?php

namespace App\Models\Setting;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected static function newFactory()
    {
        return SettingFactory::new();
    }

    protected $table = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'type',
    ];

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
