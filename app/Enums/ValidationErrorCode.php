<?php

namespace App\Enums;

/**
 * Why a field failed validation. The frontend turns each code into translated text.
 *
 * Rule codes are the snake_case rule name, so a Laravel rule or an App\Rules class is picked up by adding its case
 * here. A rule without a case goes out as `invalid`.
 */
enum ValidationErrorCode: string
{
    case Accepted = 'accepted';
    case Array = 'array';
    case Boolean = 'boolean';
    case Confirmed = 'confirmed';
    case Date = 'date';
    case Distinct = 'distinct';
    case Enum = 'enum';
    case Exists = 'exists';
    case File = 'file';
    case In = 'in';
    case Integer = 'integer';
    case Max = 'max';
    case Min = 'min';
    case NotIn = 'not_in';
    case Numeric = 'numeric';
    case Required = 'required';
    case String = 'string';
    case Unique = 'unique';
    case Url = 'url';

    case AbsolutePath = 'absolute_path';
    case UniqueIgnoringCase = 'unique_ignoring_case';

    case InvalidCredentials = 'invalid_credentials';
    case NoVideoFile = 'no_video_file';
    case OnlyPrimaryTitle = 'only_primary_title';
    case VideoBasePathUnavailable = 'video_base_path_unavailable';
    case VideoFileCheckRunning = 'video_file_check_running';

    case Invalid = 'invalid';
}
