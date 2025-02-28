<?php

namespace Dashed\DashedTranslations\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dashed\DashedTranslations\DashedTranslations
 */
class DashedTranslations extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dashed-translations';
    }
}
