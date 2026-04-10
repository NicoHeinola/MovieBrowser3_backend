<?php

namespace App\Dtos\Show;

use const Cerbero\Dto\PARTIAL;

use App\Models\Show\Show;
use Cerbero\LaravelDto\Dto;

/**
 * @property Show $show
 * @property string $bannerUrl
 * @property string $cardImageUrl
 * @property string|null $previewUrl
 * @property ShowTitleData[] $titles
 */
class UpdateShowData extends Dto
{
    protected static $defaultFlags = PARTIAL;
}
