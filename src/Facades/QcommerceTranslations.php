<?php

namespace Qubiqx\QcommerceTranslations\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Qubiqx\QcommerceTranslations\QcommerceTranslations
 */
class QcommerceTranslations extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'qcommerce-translations';
    }
}
