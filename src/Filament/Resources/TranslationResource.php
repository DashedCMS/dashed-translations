<?php

namespace Qubiqx\QcommerceTranslations\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Qubiqx\QcommerceTranslations\Filament\Resources\TranslationResource\Pages\ListTranslations;
use Qubiqx\QcommerceTranslations\Models\Translation;

class TranslationResource extends Resource
{
    protected static ?string $model = Translation::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-translate';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Vertalingen';
    protected static ?string $label = 'Vertaling';
    protected static ?string $pluralLabel = 'Vertalingen';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'tag',
            'name',
            'default',
            'value',
            'type',
        ];
    }

    public static function form(Form $form): Form
    {
        return [];
    }

    public static function table(Table $table): Table
    {
        return [];
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
            'index' => ListTranslations::route('/'),
        ];
    }
}
