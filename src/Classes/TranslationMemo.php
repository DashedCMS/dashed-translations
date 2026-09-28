<?php

namespace Dashed\DashedTranslations\Classes;

/**
 * Vertalingen die in dit request of deze queue-job al zijn opgehaald. Scoped
 * gebonden: Laravel gooit de instantie weg tussen twee Octane-requests en twee
 * queue-jobs, zodat een langlopende worker geen verouderde tekst toont.
 */
class TranslationMemo
{
    private array $values = [];

    public function remember(string $key, callable $resolve): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        return $this->values[$key] = $resolve();
    }

    public function flush(): void
    {
        $this->values = [];
    }
}
