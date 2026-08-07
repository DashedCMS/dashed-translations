<?php

namespace Dashed\DashedTranslations\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Jobs\StartTranslationOfModel;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource\Pages\ListAutomatedTranslationProgress;

class AutomatedTranslationProgressResource extends Resource
{
    protected static ?string $model = AutomatedTranslationProgress::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-language';
    protected static string | UnitEnum | null $navigationGroup = 'Vertalingen';
    protected static ?int $navigationSort = 20;
    protected static ?string $navigationLabel = 'Automatische vertaling';
    protected static ?string $label = 'Vertaling';
    protected static ?string $pluralLabel = 'Automatische vertalingen';

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

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')
                ->label(__('ID'))
                ->searchable()
                ->sortable(),
            TextColumn::make('model.name')
                ->label(__('Naam'))
                ->searchable()
                ->formatStateUsing(fn ($record) => str($record->model->name)->limit(30))
                ->tooltip(function ($record) {
                    if (! $record->model) {
                        return null;
                    }

                    $translated = (method_exists($record->model, 'isTranslatableAttribute') && $record->model->isTranslatableAttribute('name'))
                        ? $record->model->getTranslation('name', $record->to_locale, false)
                        : null;

                    if (! $translated) {
                        return 'Nog niet vertaald naar ' . strtoupper($record->to_locale);
                    }

                    return strtoupper($record->to_locale) . ': ' . $translated;
                }),
            TextColumn::make('model')
                ->label(__('Model'))
                ->searchable()
                ->getStateUsing(fn ($record) => str($record->model_type)->explode('\\')->last())
                ->sortable(),
            TextColumn::make('model_id')
                ->label(__('Model ID'))
                ->sortable()
                ->searchable(),
            TextColumn::make('from_locale')
                ->label(__('Vanaf taal'))
                ->searchable()
                ->sortable(),
            TextColumn::make('to_locale')
                ->label(__('Naar taal'))
                ->searchable()
                ->sortable(),
            TextColumn::make('total_strings_to_translate')
                ->label(__('Voortgang'))
                ->formatStateUsing(fn ($record) => $record->status == 'finished' ? '100%' : ((! $record->total_strings_to_translate || ! $record->total_strings_translated) ? '0%' : ((number_format(100 / $record->total_strings_to_translate * $record->total_strings_translated, 0)) . '%'))),
            TextColumn::make('status')
                ->label(__('Status'))
                ->formatStateUsing(fn ($record) => match ($record->status) {
                    'pending' => 'In afwachting',
                    'in_progress' => 'Bezig',
                    'finished' => 'Voltooid',
                    'error' => 'Foutmelding',
                    'retrying' => 'Opnieuw proberen',
                    default => 'Onbekend',
                })
                ->sortable()
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'primary',
                    'in_progress' => 'warning',
                    'finished' => 'success',
                    'error' => 'danger',
                    'retrying' => 'warning',
                }),
            TextColumn::make('error')
                ->label(__('Foutmelding'))
                ->getStateUsing(fn ($record) => $record->error ?: '-')
                ->sortable(),
            TextColumn::make('created_at')
                ->label(__('Aangemaakt op'))
                ->sortable()
                ->dateTime(),
        ])
            ->recordActions([
                Action::make('translateAgain')
                    ->label(__('Opnieuw vertalen'))
                    ->icon('heroicon-o-language')
                    ->button()
                    ->action(function (AutomatedTranslationProgress $record) {
                        StartTranslationOfModel::dispatch($record->model, $record->from_locale, [$record->to_locale], [], $record);
                        //                        AutomatedTranslation::translateModel($record->model, $record->from_locale, [$record->to_locale], [], $record);

                        Notification::make()
                            ->success()
                            ->title(__('De automatische vertaling van :naam is opnieuw gestart', ['naam' => $record->model->name]))
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
                BulkAction::make('translateAgain')
                    ->label(__('Opnieuw vertalen'))
                    ->icon('heroicon-o-language')
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            StartTranslationOfModel::dispatch($record->model, $record->from_locale, [$record->to_locale], [], $record);
                            //                            AutomatedTranslation::translateModel($record->model, $record->from_locale, [$record->to_locale], [], $record);
                        }

                        Notification::make()
                            ->success()
                            ->title(__('De automatische vertaling van :aantal records is opnieuw gestart', ['aantal' => count($records)]))
                            ->send();
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->multiple()
                    ->preload()
                    ->options([
                        'pending' => __('In afwachting'),
                        'in_progress' => __('Bezig'),
                        'finished' => __('Voltooid'),
                    ]),
                SelectFilter::make('model_type')
                    ->label(__('Model'))
                    ->multiple()
                    ->preload()
                    ->options(function () {
                        $options = AutomatedTranslationProgress::select('model_type')->distinct()->get()->pluck('model_type', 'model_type');
                        foreach ($options as $key => $value) {
                            $options[$key] = str($value)->explode('\\')->last();
                        }

                        return $options;
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->poll('5s');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomatedTranslationProgress::route('/'),
        ];
    }
}
