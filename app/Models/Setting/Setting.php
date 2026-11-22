<?php

namespace App\Models\Setting;

use Database\Factories\SettingFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

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
     * Get the value attribute, cast based on the type. A `secret` is stored encrypted with the app key; empty stays
     * empty.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => match ($this->type) {
                'json' => json_decode((string) $value, true),
                'number' => is_numeric($value) ? (str_contains((string) $value, '.') ? (float) $value : (int) $value) : $value,
                'secret' => $this->decryptSecret($value),
                default => $value,
            },
            set: fn (mixed $value) => match ($this->type) {
                'json' => json_encode($value),
                'secret' => filled($value) ? Crypt::encryptString((string) $value) : '',
                default => (string) $value,
            }
        );
    }

    /**
     * A secret encrypted with an earlier app key can't be read any more, so it counts as not set and the admin enters
     * it again, instead of every settings read failing.
     */
    private function decryptSecret(mixed $value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            return '';
        }
    }
}
