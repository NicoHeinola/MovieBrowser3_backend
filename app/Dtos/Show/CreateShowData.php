<?php

namespace App\Dtos\Show;

use Cerbero\LaravelDto\Dto;

/**
 * @property string $bannerUrl
 * @property string $cardImageUrl
 * @property string|null $previewUrl
 * @property ShowTitleData[] $titles
 */
class CreateShowData extends Dto {}
