<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Classes\Translatables;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Metadata;
use Dashed\DashedCore\Models\CustomBlock;
use Dashed\DashedCore\Models\Concerns\HasMetadata;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Eén vertaalbaar model zoals een pakket het aanmeldt: hoe het heet in het
 * overzicht, welke kinderen met de ouder meevertaald worden en dus in de
 * vingerafdruk van de ouder meetellen, en hoe je naar de bewerkpagina komt.
 */
final class Translatable
{
    private string|Closure $label = '';

    private string|Closure $group = '';

    /** @var array<string, array{class: string, parent: Closure}> relatienaam => kind */
    private array $children = [];

    private ?Closure $name = null;

    private ?Closure $url = null;

    private ?Closure $tag = null;

    private readonly string $model;

    /**
     * Zonder leading backslash, zodat een registratie met '\\Foo\\Bar' en een
     * vind(Bar::class) op dezelfde sleutel uitkomen.
     */
    private function __construct(string $model)
    {
        $this->model = ltrim($model, '\\');
    }

    public static function make(string $model): self
    {
        return new self($model);
    }

    public function label(string|Closure $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function group(string|Closure $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Een relatie waarvan de rijen met de ouder meevertaald worden. $parent
     * krijgt het kind en geeft de ouder terug (of null als die er niet is).
     */
    public function child(string $relation, string $childClass, Closure $parent): self
    {
        $this->children[$relation] = ['class' => ltrim($childClass, '\\'), 'parent' => $parent];

        return $this;
    }

    /**
     * Metadata en CustomBlock als kind, voor zover het model die traits heeft.
     * Dat zijn precies de twee die translateModel() met de ouder meestuurt.
     */
    public function contentChildren(): self
    {
        $traits = class_uses_recursive($this->model);

        if (in_array(HasMetadata::class, $traits, true)) {
            $this->child('metadata', Metadata::class, fn (Metadata $metadata) => self::morphParent($metadata, 'metadatable'));
        }

        if (in_array(HasCustomBlocks::class, $traits, true)) {
            $this->child('customBlocks', CustomBlock::class, fn (CustomBlock $block) => self::morphParent($block, 'blockable'));
        }

        return $this;
    }

    public function name(Closure $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function url(Closure $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Bewerk-url via een Filament-resource. rescue(): niet elke resource heeft
     * een edit-pagina, en een ontbrekende route mag het scherm niet breken.
     */
    public function urlVia(string $resourceClass): self
    {
        return $this->url(fn (Model $record) => class_exists($resourceClass)
            ? rescue(fn () => $resourceClass::getUrl('edit', ['record' => $record]), null, false)
            : null);
    }

    public function tag(Closure $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function modelClass(): string
    {
        return $this->model;
    }

    public function labelText(): string
    {
        return (string) value($this->label);
    }

    public function groupText(): string
    {
        return (string) value($this->group);
    }

    /** @return array<string, array{class: string, parent: Closure}> */
    public function children(): array
    {
        return $this->children;
    }

    public function nameFor(Model $record): string
    {
        if ($this->name) {
            return (string) ($this->name)($record);
        }

        $name = $record->name ?? $record->title ?? null;

        return is_string($name) && $name !== '' ? $name : '#' . $record->getKey();
    }

    public function urlFor(Model $record): ?string
    {
        return $this->url ? ($this->url)($record) : null;
    }

    public function tagFor(Model $record): ?string
    {
        return $this->tag ? ($this->tag)($record) : null;
    }

    /**
     * De ouder van een morph-kind via zijn eigen kolommen, want Metadata heeft
     * geen morphTo-relatie en CustomBlock noemt hem `model()`.
     */
    public static function morphParent(Model $child, string $prefix): ?Model
    {
        $type = $child->{$prefix . '_type'};
        $id = $child->{$prefix . '_id'};

        if (! $type || ! $id) {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) ? $class::query()->find($id) : null;
    }
}
