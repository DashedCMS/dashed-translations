<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Filament\Resources;

use BackedEnum;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedCore\Classes\QueryHelpers\TokenizedSearch;
use Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Classes\Translatables\RunningTranslations;
use Dashed\DashedTranslations\Classes\Translatables\TranslatableRegistry;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStarter;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;
use Dashed\DashedTranslations\Filament\Resources\TranslationStateResource\Pages\ListTranslationStates;
use Dashed\DashedTranslations\Jobs\TranslateOutdatedJob;
use Dashed\DashedTranslations\Models\TranslationState;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema as DbSchema;
use UnitEnum;
use WeakMap;

class TranslationStateResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = TranslationState::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Vertalingen';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Vertaalstatus';

    protected static ?string $label = 'Vertaalstatus';

    protected static ?string $pluralLabel = 'Vertaalstatus';

    public static function shouldRegisterNavigation(): bool
    {
        return AutomatedTranslation::automatedTranslationsEnabled();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('from_locale', TranslationStateUpdater::fromLocale())
            ->inRegister();
    }

    public static function table(Table $table): Table
    {
        $registry = app(TranslatableRegistry::class);

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->getStateUsing(fn (TranslationState $record) => $record->model && ($entry = $registry->vind($record->model_type))
                        ? $entry->nameFor($record->model)
                        : '#'.$record->model_id)
                    ->searchable(query: fn (Builder $query, string $search) => self::applyNameSearch($query, $registry, $search))
                    ->limit(50),
                TextColumn::make('model_type')
                    ->label(__('Soort'))
                    ->formatStateUsing(fn (string $state) => $registry->vind($state)?->labelText() ?? class_basename($state)),
                TextColumn::make('to_locale')
                    ->label(__('Doeltaal'))
                    ->formatStateUsing(fn (string $state) => strtoupper($state)),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->getStateUsing(function (TranslationState $record) {
                        $running = RunningTranslations::for($record->model_type, (int) $record->model_id, $record->to_locale);

                        if ($running && $running->status === 'error') {
                            return 'error';
                        }

                        return $running ? 'running' : $record->status;
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'running' => __('Bezig'),
                        'error' => __('Fout'),
                        default => TranslationState::labels()[$state] ?? $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        TranslationState::STATUS_MISSING, 'error' => 'danger',
                        TranslationState::STATUS_OUTDATED => 'warning',
                        TranslationState::STATUS_MANUAL => 'info',
                        TranslationState::STATUS_TRANSLATED => 'success',
                        TranslationState::STATUS_EXCLUDED, 'running' => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(function (TranslationState $record) {
                        $running = RunningTranslations::for($record->model_type, (int) $record->model_id, $record->to_locale);

                        if ($running && $running->status === 'error') {
                            return $running->error;
                        }

                        if ($running) {
                            return $running->total_strings_translated.' / '.$running->total_strings_to_translate;
                        }

                        return $record->status === TranslationState::STATUS_MANUAL && $record->source_changed
                            ? __('Bron gewijzigd sinds de laatste vertaling')
                            : null;
                    }),
                TextColumn::make('translated_at')
                    ->label(__('Laatst vertaald'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('model.updated_at')
                    ->label(__('Laatst gewijzigd'))
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->groups([
                Group::make('model_type')
                    ->label(__('Soort'))
                    ->getTitleFromRecordUsing(fn (TranslationState $record) => $registry->vind($record->model_type)?->labelText() ?? class_basename($record->model_type)),
            ])
            ->defaultGroup('model_type')
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw("CASE status WHEN 'missing' THEN 0 WHEN 'outdated' THEN 1 WHEN 'manual' THEN 2 WHEN 'translated' THEN 3 ELSE 4 END")
                ->orderBy('model_id'))
            ->filters([
                SelectFilter::make('model_type')
                    ->label(__('Soort'))
                    ->multiple()
                    ->options(fn () => collect($registry->alles())->mapWithKeys(fn ($e) => [$e->modelClass() => $e->labelText()])->all()),
                SelectFilter::make('to_locale')
                    ->label(__('Doeltaal'))
                    ->multiple()
                    ->options(fn () => collect(TranslationStateUpdater::toLocales())->mapWithKeys(fn ($l) => [$l => strtoupper($l)])->all()),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->multiple()
                    ->options(TranslationState::labels()),
                Filter::make('translated_at')
                    ->label(__('Laatst vertaald'))
                    ->schema([
                        Select::make('ooit')
                            ->label(__('Laatst vertaald'))
                            ->options([
                                'nooit' => __('Nog nooit vertaald'),
                                'ooit' => __('Ooit vertaald'),
                            ])
                            ->placeholder(__('Maakt niet uit')),
                        DatePicker::make('vanaf')->label(__('Vertaald vanaf')),
                        DatePicker::make('tot')->label(__('Vertaald tot en met')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(($data['ooit'] ?? null) === 'nooit', fn (Builder $q) => $q->whereNull('translated_at'))
                        ->when(($data['ooit'] ?? null) === 'ooit', fn (Builder $q) => $q->whereNotNull('translated_at'))
                        ->when($data['vanaf'] ?? null, fn (Builder $q, $vanaf) => $q->whereDate('translated_at', '>=', $vanaf))
                        ->when($data['tot'] ?? null, fn (Builder $q, $tot) => $q->whereDate('translated_at', '<=', $tot)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (($data['ooit'] ?? null) === 'nooit') {
                            $indicators[] = __('Nog nooit vertaald');
                        } elseif (($data['ooit'] ?? null) === 'ooit') {
                            $indicators[] = __('Ooit vertaald');
                        }

                        if ($data['vanaf'] ?? null) {
                            $indicators[] = __('Vertaald vanaf').' '.$data['vanaf'];
                        }

                        if ($data['tot'] ?? null) {
                            $indicators[] = __('Vertaald tot en met').' '.$data['tot'];
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Action::make('translate')
                    ->label(__('Vertaal'))
                    ->icon('heroicon-o-language')
                    ->visible(fn (TranslationState $record) => ! $record->excluded)
                    ->requiresConfirmation(fn (TranslationState $record) => $record->status === TranslationState::STATUS_MANUAL)
                    ->modalDescription(__('Deze vertaling is met de hand aangepast. Opnieuw vertalen overschrijft dat handwerk.'))
                    ->action(function (TranslationState $record) {
                        if (! $record->model) {
                            return;
                        }

                        // Via TranslationStarter en niet rechtstreeks via
                        // translateModel(): zie de toelichting op die klasse.
                        // Zonder dit blijft bijvoorbeeld een formulier met
                        // velden op 'missing' staan terwijl deze knop
                        // "gestart" meldt.
                        TranslationStarter::start($record->model, $record->from_locale, [$record->to_locale]);

                        Notification::make()->title(__('Vertaling gestart, dit kan even duren.'))->warning()->send();
                    }),
                Action::make('exclude')
                    ->label(__('Niet vertalen'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->visible(fn (TranslationState $record) => ! $record->excluded && $record->model)
                    ->requiresConfirmation()
                    ->modalDescription(__('Dit item wordt in alle talen overgeslagen door de vertaalrondes en de knoppen op dit scherm, totdat je op "Toch vertalen" klikt.'))
                    ->action(function (TranslationState $record) {
                        TranslationStateUpdater::exclude($record->model);

                        Notification::make()->title(__('Item uitgesloten van vertalen.'))->success()->send();
                    }),
                Action::make('include')
                    ->label(__('Toch vertalen'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (TranslationState $record) => $record->excluded && $record->model)
                    ->action(function (TranslationState $record) {
                        TranslationStateUpdater::include($record->model);

                        Notification::make()->title(__('Item doet weer mee met vertalen.'))->success()->send();
                    }),
                Action::make('open')
                    ->label(__('Open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (TranslationState $record) => self::openUrlFor($registry, $record))
                    ->visible(fn (TranslationState $record) => filled(self::openUrlFor($registry, $record)))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkAction::make('translateSelection')
                    ->label(__('Vertaal selectie'))
                    ->icon('heroicon-o-language')
                    ->schema([
                        Toggle::make('include_manual')
                            ->label(__('Ook handmatig aangepaste items overschrijven'))
                            ->helperText(__('Uit betekent dat handmatig aangepaste items in de selectie overgeslagen worden.'))
                            ->default(false),
                    ])
                    ->action(function (Collection $records, array $data) {
                        TranslateOutdatedJob::dispatch(
                            $records->pluck('to_locale')->unique()->values()->all(),
                            (bool) ($data['include_manual'] ?? false),
                            null,
                            $records->pluck('id')->map(fn ($id) => (int) $id)->all(),
                        );

                        Notification::make()->title(__('Vertalingen gestart, dit kan even duren.'))->warning()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('excludeSelection')
                    ->label(__('Niet vertalen'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(__('De geselecteerde items worden in alle talen overgeslagen door de vertaalrondes, totdat je ze weer op "Toch vertalen" zet.'))
                    ->action(function (Collection $records) {
                        foreach (self::uniekeModellen($records) as $model) {
                            TranslationStateUpdater::exclude($model);
                        }

                        Notification::make()->title(__('Items uitgesloten van vertalen.'))->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('includeSelection')
                    ->label(__('Toch vertalen'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->action(function (Collection $records) {
                        foreach (self::uniekeModellen($records) as $model) {
                            TranslationStateUpdater::include($model);
                        }

                        Notification::make()->title(__('Items doen weer mee met vertalen.'))->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->poll('5s');
    }

    public static function translateOutdatedAction(bool $includeManual): Action
    {
        $name = $includeManual ? 'translateIncludingManual' : 'translateOutdated';
        $statuses = [TranslationState::STATUS_MISSING, TranslationState::STATUS_OUTDATED];

        if ($includeManual) {
            $statuses[] = TranslationState::STATUS_MANUAL;
        }

        return Action::make($name)
            ->label($includeManual ? __('Vertaal ook handmatig aangepaste items') : __('Vertaal alles wat ontbreekt of verouderd is'))
            ->icon('heroicon-o-language')
            ->color($includeManual ? 'danger' : 'primary')
            ->requiresConfirmation($includeManual)
            ->modalDescription(function () use ($statuses) {
                $perTaal = TranslationState::query()
                    ->inRegister()
                    ->where('from_locale', TranslationStateUpdater::fromLocale())
                    ->whereIn('status', $statuses)
                    ->selectRaw('to_locale, count(*) as aantal')
                    ->groupBy('to_locale')
                    ->pluck('aantal', 'to_locale');

                return $perTaal->map(fn ($aantal, $taal) => strtoupper($taal).': '.$aantal)->implode(', ') ?: __('Niets te doen.');
            })
            ->schema([
                Select::make('to_locales')
                    ->label(__('Naar talen'))
                    ->options(fn () => collect(TranslationStateUpdater::toLocales())->mapWithKeys(fn ($l) => [$l => Locales::getLocalesArray()[$l] ?? strtoupper($l)])->all())
                    ->default(fn () => TranslationStateUpdater::toLocales())
                    ->multiple()
                    ->required(),
            ])
            ->action(function (array $data) use ($includeManual) {
                // Zelfde plafond als de nachtelijke ronde (dashed:translate-
                // outdated), uit dezelfde instelling: deze knop heeft geen
                // stateIds-selectie en kan dus in theorie de hele tabel
                // pakken, terwijl TranslateOutdatedJob timeout 600 / tries 1
                // heeft. Zonder plafond loopt de job vast op een grote
                // catalogus en meldt hij niets meer terug (tries 1, geen
                // retry). Wat niet aan de beurt komt, komt bij een volgende
                // druk op de knop of de volgende nacht.
                $limit = max(1, (int) Customsetting::get('auto_translate_max_per_run', null, '200'));

                TranslateOutdatedJob::dispatch($data['to_locales'], $includeManual, $limit);

                Notification::make()->title(__('Vertalingen gestart, dit kan even duren.'))->warning()->send();
            });
    }

    /**
     * Een selectie bevat per item één rij per doeltaal; uitsluiten en weer
     * meenemen gebeurt per item (alle talen), dus elk model één keer.
     *
     * @return array<int, Model>
     */
    protected static function uniekeModellen(Collection $records): array
    {
        return $records
            ->unique(fn (TranslationState $record) => $record->model_type.'#'.$record->model_id)
            ->map(fn (TranslationState $record) => $record->model)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * urlFor() bouwt een Filament-route-url op, geen kale property-lezing.
     * De "open"-actie vraagt dit zowel in url() als in visible() op; een
     * WeakMap per rij-record voorkomt dat dat twee keer gebeurt, zonder een
     * gewone statische cache die (met spl_object_id-hergebruik in een
     * langlevend proces) tussen rijen door elkaar zou kunnen lopen.
     */
    protected static function openUrlFor(TranslatableRegistry $registry, TranslationState $record): ?string
    {
        static $cache = null;
        $cache ??= new WeakMap();

        // offsetExists() en niet isset(): isset() geeft false terug voor een
        // opgeslagen null, en null is precies de uitkomst die visible() hier
        // moet kunnen dedupliceren (een resolvebare url() die null oplevert).
        // Met isset() werd zo'n rij dus toch twee keer uitgerekend.
        if (! $cache->offsetExists($record)) {
            $cache[$record] = $record->model ? $registry->vind($record->model_type)?->urlFor($record->model) : null;
        }

        return $cache[$record];
    }

    /**
     * Zoeken op de naam-kolom: de kolom zelf bestaat niet op TranslationState
     * (die is afgeleid via nameFor()), dus de zoekterm wordt via een
     * whereHasMorph over de geregistreerde soorten toegepast op hun eigen
     * name/title-kolom. TokenizedSearch en geen handgeschreven LIKE (zie
     * CLAUDE.md, "Breed zoeken"). Per soort wordt alleen de kolom gebruikt
     * die er echt is: Schema::hasColumn() voorkomt een SQL-fout op een model
     * dat geen 'name' of 'title' heeft.
     */
    protected static function applyNameSearch(Builder $query, TranslatableRegistry $registry, string $search): Builder
    {
        $classes = array_map(fn ($entry) => $entry->modelClass(), $registry->alles());

        return $query->whereHasMorph('model', $classes, function (Builder $q, string $type) use ($search) {
            $kolommen = array_values(array_filter(
                ['name', 'title'],
                fn ($kolom) => class_exists($type) && DbSchema::hasColumn((new $type())->getTable(), $kolom)
            ));

            if ($kolommen === []) {
                $q->whereRaw('1 = 0');

                return;
            }

            TokenizedSearch::apply($q, $search, $kolommen);
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTranslationStates::route('/'),
        ];
    }
}
