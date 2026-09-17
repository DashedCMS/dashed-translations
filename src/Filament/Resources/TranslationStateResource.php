<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Filament\Resources;

use WeakMap;
use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Grouping\Group;
use Illuminate\Support\Collection;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedTranslations\Models\TranslationState;
use Dashed\DashedTranslations\Jobs\TranslateOutdatedJob;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Classes\Translatables\RunningTranslations;
use Dashed\DashedTranslations\Classes\Translatables\TranslatableRegistry;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;
use Dashed\DashedTranslations\Filament\Resources\TranslationStateResource\Pages\ListTranslationStates;

class TranslationStateResource extends Resource
{
    protected static ?string $model = TranslationState::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string | UnitEnum | null $navigationGroup = 'Vertalingen';
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
            ->whereHasMorph('model', '*');
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
                        : '#' . $record->model_id)
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
                        default => 'gray',
                    })
                    ->tooltip(function (TranslationState $record) {
                        $running = RunningTranslations::for($record->model_type, (int) $record->model_id, $record->to_locale);

                        if ($running && $running->status === 'error') {
                            return $running->error;
                        }

                        if ($running) {
                            return $running->total_strings_translated . ' / ' . $running->total_strings_to_translate;
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
                ->orderByRaw("CASE status WHEN 'missing' THEN 0 WHEN 'outdated' THEN 1 WHEN 'manual' THEN 2 ELSE 3 END")
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
            ])
            ->recordActions([
                Action::make('translate')
                    ->label(__('Vertaal'))
                    ->icon('heroicon-o-language')
                    ->requiresConfirmation(fn (TranslationState $record) => $record->status === TranslationState::STATUS_MANUAL)
                    ->modalDescription(__('Deze vertaling is met de hand aangepast. Opnieuw vertalen overschrijft dat handwerk.'))
                    ->action(function (TranslationState $record) {
                        if (! $record->model) {
                            return;
                        }

                        AutomatedTranslation::translateModel($record->model, $record->from_locale, [$record->to_locale]);

                        Notification::make()->title(__('Vertaling gestart, dit kan even duren.'))->warning()->send();
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
                    ->where('from_locale', TranslationStateUpdater::fromLocale())
                    ->whereIn('status', $statuses)
                    ->selectRaw('to_locale, count(*) as aantal')
                    ->groupBy('to_locale')
                    ->pluck('aantal', 'to_locale');

                return $perTaal->map(fn ($aantal, $taal) => strtoupper($taal) . ': ' . $aantal)->implode(', ') ?: __('Niets te doen.');
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
                TranslateOutdatedJob::dispatch($data['to_locales'], $includeManual);

                Notification::make()->title(__('Vertalingen gestart, dit kan even duren.'))->warning()->send();
            });
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

        if (! isset($cache[$record])) {
            $cache[$record] = $record->model ? $registry->vind($record->model_type)?->urlFor($record->model) : null;
        }

        return $cache[$record];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTranslationStates::route('/'),
        ];
    }
}
