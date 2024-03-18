<?php

namespace Dashed\DashedTranslations\Filament\Resources;

use Dashed\DashedTranslations\Filament\Resources\TranslationResource\Pages\ListTranslations;
use Dashed\DashedTranslations\Models\Translation;
<<<<<<< HEAD
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
=======
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
>>>>>>> b79745b7f3b802274820f98a264a853f28cf727f

class TranslationResource extends Resource
{
    protected static ?string $model = Translation::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-language';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Vertalingen';
    protected static ?string $label = 'Vertaling';
    protected static ?string $pluralLabel = 'Vertalingen';

    public static function getGloballySearchableAttributes(): array
    {
        return [];
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
