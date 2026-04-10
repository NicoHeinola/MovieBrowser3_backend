<?php

namespace App\Dtos\Setting;

use App\Models\Setting\Setting;
use Spatie\LaravelData\Data;

class UpdateSettingData extends Data
{
    public function __construct(
        public Setting $setting,
        public mixed $value,
    ) {}
}
