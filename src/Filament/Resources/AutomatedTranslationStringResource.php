<?php

namespace Dashed\DashedTranslations\Filament\Resources;

use BackedEnum;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationStringResource\Pages\ListAutomatedTranslationStrings;
use Dashed\DashedTranslations\Models\AutomatedTranslationString;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class AutomatedTranslationStringResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;

    protected static ?string $model = AutomatedTranslationString::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Vertalingen';
    protected static ?string $navigationLabel = 'Vertalings-cache';
    protected static ?string $label = 'Cached vertaling';
    protected static ?string $pluralLabel = 'Cached vertalingen';
    protected static ?int $navigationSort = 99;

    // Zonder eigen policy viel deze resource terug op default-allow. Vereis expliciet
    // het beheerrecht voor (automatische) vertalingen.
    public static function canAccess(): bool
    {
        return auth()->user()?->can('edit_automated_translation_progress') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return AutomatedTranslation::automatedTranslationsEnabled();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Vertaling'))
                ->columns(2)
                ->schema([
                    TextInput::make('from_locale')
                        ->label(__('Van taal'))
                        ->disabled(),
                    TextInput::make('to_locale')
                        ->label(__('Naar taal'))
                        ->disabled(),
                    Textarea::make('from_string')
                        ->label(__('Bron-tekst'))
                        ->rows(3)
                        ->columnSpanFull()
                        ->disabled(),
                    Textarea::make('to_string')
                        ->label(__('Vertaalde tekst'))
                        ->rows(3)
                        ->columnSpanFull()
                        ->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('from_locale')
                    ->label(__('Van'))
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->sortable(),
                TextColumn::make('to_locale')
                    ->label(__('Naar'))
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->sortable(),
                TextColumn::make('from_string')
                    ->label(__('Bron'))
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->from_string)
                    ->searchable(),
                TextColumn::make('to_string')
                    ->label(__('Vertaling'))
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->to_string)
                    ->searchable()
                    ->placeholder(__('- nog niet vertaald -')),
                IconColumn::make('translated')
                    ->label(__('Vertaald'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('Bijgewerkt'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('from_locale')
                    ->label(__('Van taal'))
                    ->options(fn () => AutomatedTranslationString::query()
                        ->distinct()
                        ->pluck('from_locale', 'from_locale')
                        ->map(fn ($v) => strtoupper($v))
                        ->all()),
                SelectFilter::make('to_locale')
                    ->label(__('Naar taal'))
                    ->options(fn () => AutomatedTranslationString::query()
                        ->distinct()
                        ->pluck('to_locale', 'to_locale')
                        ->map(fn ($v) => strtoupper($v))
                        ->all()),
                TernaryFilter::make('translated')
                    ->label(__('Vertaald')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resetTranslation')
                    ->label(__('Reset'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Vertaling resetten'))
                    ->modalDescription(__('Deze gecachede vertaling wordt verwijderd, zodat de volgende vertaal-run de externe API (DeepL e.d.) opnieuw raadpleegt.'))
                    ->modalSubmitActionLabel(__('Ja, reset deze vertaling'))
                    ->action(function (AutomatedTranslationString $record) {
                        $record->update([
                            'to_string' => null,
                            'translated' => false,
                        ]);

                        $record->progress()->updateExistingPivot(
                            $record->progress()->pluck('dashed__automated_translation_progress.id'),
                            ['replaced' => false]
                        );

                        Notification::make()
                            ->title(__('Vertaling gereset'))
                            ->body(__('Volgende vertaal-run raadpleegt de externe API opnieuw.'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->label(__('Verwijder')),
            ])
            ->toolbarActions([
                BulkAction::make('resetTranslations')
                    ->label(__('Geselecteerde resetten'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Geselecteerde vertalingen resetten'))
                    ->modalDescription(__('De vertalingen worden uit de cache verwijderd. De volgende vertaal-run raadpleegt de externe API (DeepL e.d.) opnieuw voor deze regels.'))
                    ->modalSubmitActionLabel(__('Ja, reset alle geselecteerde'))
                    ->action(function (Collection $records) {
                        $ids = $records->pluck('id')->all();

                        AutomatedTranslationString::whereIn('id', $ids)->update([
                            'to_string' => null,
                            'translated' => false,
                        ]);

                        \DB::table('dashed__automated_translation_progress_string')
                            ->whereIn('automated_translation_string_id', $ids)
                            ->update(['replaced' => false]);

                        Notification::make()
                            ->title(__(':aantal vertalingen gereset', ['aantal' => count($ids)]))
                            ->body(__('Volgende vertaal-run raadpleegt de externe API opnieuw.'))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomatedTranslationStrings::route('/'),
        ];
    }
}
