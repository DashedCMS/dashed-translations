<?php

namespace Dashed\DashedTranslations\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Models\AutomatedTranslationString;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationStringResource\Pages\ListAutomatedTranslationStrings;

class AutomatedTranslationStringResource extends Resource
{
    protected static ?string $model = AutomatedTranslationString::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Vertalings-cache';
    protected static ?string $label = 'Cached vertaling';
    protected static ?string $pluralLabel = 'Cached vertalingen';
    protected static ?int $navigationSort = 99;

    public static function shouldRegisterNavigation(): bool
    {
        return AutomatedTranslation::automatedTranslationsEnabled();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Vertaling')
                ->columns(2)
                ->schema([
                    TextInput::make('from_locale')
                        ->label('Van taal')
                        ->disabled(),
                    TextInput::make('to_locale')
                        ->label('Naar taal')
                        ->disabled(),
                    Textarea::make('from_string')
                        ->label('Bron-tekst')
                        ->rows(3)
                        ->columnSpanFull()
                        ->disabled(),
                    Textarea::make('to_string')
                        ->label('Vertaalde tekst')
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
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('from_locale')
                    ->label('Van')
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->sortable(),
                TextColumn::make('to_locale')
                    ->label('Naar')
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->sortable(),
                TextColumn::make('from_string')
                    ->label('Bron')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->from_string)
                    ->searchable(),
                TextColumn::make('to_string')
                    ->label('Vertaling')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->to_string)
                    ->searchable()
                    ->placeholder('— nog niet vertaald —'),
                IconColumn::make('translated')
                    ->label('Vertaald')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('from_locale')
                    ->label('Van taal')
                    ->options(fn () => AutomatedTranslationString::query()
                        ->distinct()
                        ->pluck('from_locale', 'from_locale')
                        ->map(fn ($v) => strtoupper($v))
                        ->all()),
                SelectFilter::make('to_locale')
                    ->label('Naar taal')
                    ->options(fn () => AutomatedTranslationString::query()
                        ->distinct()
                        ->pluck('to_locale', 'to_locale')
                        ->map(fn ($v) => strtoupper($v))
                        ->all()),
                TernaryFilter::make('translated')
                    ->label('Vertaald'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resetTranslation')
                    ->label('Reset')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Vertaling resetten')
                    ->modalDescription('Deze gecachede vertaling wordt verwijderd, zodat de volgende vertaal-run de externe API (DeepL e.d.) opnieuw raadpleegt.')
                    ->modalSubmitActionLabel('Ja, reset deze vertaling')
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
                            ->title('Vertaling gereset')
                            ->body('Volgende vertaal-run raadpleegt de externe API opnieuw.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->label('Verwijder'),
            ])
            ->toolbarActions([
                BulkAction::make('resetTranslations')
                    ->label('Geselecteerde resetten')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Geselecteerde vertalingen resetten')
                    ->modalDescription('De vertalingen worden uit de cache verwijderd. De volgende vertaal-run raadpleegt de externe API (DeepL e.d.) opnieuw voor deze regels.')
                    ->modalSubmitActionLabel('Ja, reset alle geselecteerde')
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
                            ->title(count($ids) . ' vertalingen gereset')
                            ->body('Volgende vertaal-run raadpleegt de externe API opnieuw.')
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
