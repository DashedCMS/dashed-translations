<?php

namespace Dashed\DashedTranslations\Filament\Resources;

use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource\Pages\ListAutomatedTranslationProgress;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AutomatedTranslationProgressResource extends Resource
{
    protected static ?string $model = AutomatedTranslationProgress::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-language';
    protected static ?string $navigationGroup = 'Content';
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

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('model')
                ->label('Model')
                ->getStateUsing(fn($record) => $record->model_type . ' - ' . $record->model_id),
            TextColumn::make('from_locale')
                ->label('Vanaf taal')
                ->sortable(),
            TextColumn::make('to_locale')
                ->label('Naar taal')
                ->sortable(),
            TextColumn::make('total_columns_to_translate')
                ->label('Voortgang')
                ->formatStateUsing(fn($record) => $record->total_columns_translated . '/' . $record->total_columns_to_translate),
            TextColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn($record) => match ($record->status) {
                    'pending' => 'In afwachting',
                    'in_progress' => 'Bezig',
                    'finished' => 'Voltooid',
                    default => 'Onbekend',
                })
                ->sortable()
                ->badge()
                ->color(fn(string $state): string => match ($state) {
                    'pending' => 'primary',
                    'in_progress' => 'warning',
                    'finished' => 'success',
                }),
            TextColumn::make('created_at')
                ->label('Aangemaakt op')
                ->sortable()
                ->dateTime(),
        ])
            ->defaultSort('created_at', 'desc')
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
