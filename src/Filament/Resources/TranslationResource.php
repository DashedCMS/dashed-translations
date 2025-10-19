<?php

namespace Dashed\DashedTranslations\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource\Pages\ListTranslations;

class TranslationResource extends Resource
{
    protected static ?string $model = Translation::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-language';
    protected static string | UnitEnum | null $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Vertalingen';
    protected static ?string $label = 'Vertaling';
    protected static ?string $pluralLabel = 'Vertalingen';

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function form(Schema $schema): Schema
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
