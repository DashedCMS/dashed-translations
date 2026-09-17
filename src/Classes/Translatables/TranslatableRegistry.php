<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Illuminate\Database\Eloquent\Model;

/**
 * In-memory register van alles wat in het vertaalstatus-overzicht hoort.
 * Gebonden als singleton in registeringPackage() van dashed-translations,
 * zodat elk pakket in zijn bootingPackage() in dezelfde instantie schrijft.
 */
class TranslatableRegistry
{
    /** @var array<string, Translatable> modelklasse => registratie */
    protected array $entries = [];

    public static function register(Translatable $translatable): void
    {
        app(self::class)->registreer($translatable);
    }

    public function registreer(Translatable $translatable): void
    {
        $this->entries[$translatable->modelClass()] = $translatable;
    }

    /** @return array<int, Translatable> */
    public function alles(): array
    {
        return array_values($this->entries);
    }

    public function vind(string $modelClass): ?Translatable
    {
        return $this->entries[$modelClass] ?? null;
    }

    public function isKind(string $modelClass): bool
    {
        foreach ($this->entries as $entry) {
            foreach ($entry->children() as $child) {
                if ($child['class'] === $modelClass) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * De geregistreerde ouder van een kind, of null als het model geen kind is
     * of zijn ouder niet (meer) bestaat.
     */
    public function ouderVan(Model $child): ?Model
    {
        foreach ($this->entries as $entry) {
            foreach ($entry->children() as $definition) {
                if ($definition['class'] !== $child::class) {
                    continue;
                }

                $parent = ($definition['parent'])($child);

                if ($parent instanceof Model && $parent::class === $entry->modelClass()) {
                    return $parent;
                }
            }
        }

        return null;
    }

    public function flush(): void
    {
        $this->entries = [];
    }

    /**
     * Hangt de listeners aan. Elke boot opnieuw, zonder statische
     * "al gedaan"-vlag: die overleeft in tests de herstart van de applicatie
     * en hangt de listener dan aan een dispatcher die niet meer bestaat.
     */
    public function boot(): void
    {
        foreach ($this->entries as $entry) {
            $class = $entry->modelClass();

            if (! class_exists($class)) {
                continue;
            }

            $class::saved(fn (Model $model) => rescue(fn () => TranslationStateUpdater::refresh($model), report: false));
            $class::deleted(function (Model $model) {
                if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                    return;
                }

                rescue(fn () => TranslationStateUpdater::forget($model), report: false);
            });

            foreach ($entry->children() as $definition) {
                $childClass = $definition['class'];

                if (class_exists($childClass)) {
                    $childClass::saved(fn (Model $child) => rescue(fn () => TranslationStateUpdater::refresh($child), report: false));
                }
            }
        }
    }
}
