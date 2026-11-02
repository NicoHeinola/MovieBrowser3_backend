<?php

namespace App\Models\LegacyShowImport;

use App\Models\LegacyShowImport\Relations\HasLegacyShowImportRelations;
use Database\Factories\LegacyShowImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Which MovieBrowser2 show a show was imported from, so an import run skips it next time.
 */
#[Fillable(['legacy_show_id', 'show_id'])]
class LegacyShowImport extends Model
{
    /** @use HasFactory<LegacyShowImportFactory> */
    use HasFactory;

    use HasLegacyShowImportRelations;

    protected static function newFactory(): LegacyShowImportFactory
    {
        return LegacyShowImportFactory::new();
    }
}
