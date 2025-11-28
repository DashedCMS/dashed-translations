<?php

namespace Dashed\DashedTranslations\Filament\Resources;

use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource\Pages\EditTranslation;
use Dashed\DashedTranslations\Jobs\StartTranslationOfModel;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
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

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-language';
    protected static string|UnitEnum|null $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Vertalingen';
    protected static ?string $label = 'Vertaling';
    protected static ?string $pluralLabel = 'Vertalingen';

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
        $allLocales = [];

        foreach (Locales::getLocales() as $locale) {
            $allLocales[$locale['id']] = $locale['name'];
        }

        return $table->columns([
            TextColumn::make('tag')
                ->formatStateUsing(fn($state) => str($state)->headline()->ucfirst())
                ->searchable()
                ->sortable(),
            TextColumn::make('slug')
        ])
            ->recordActions([
                EditAction::make('edit'),
                Action::make('translateTab')
                    ->button()
                    ->icon('heroicon-m-language')
                    ->label('Vertaal tab')
                    ->visible(AutomatedTranslation::automatedTranslationsEnabled())
                    ->schema(array_merge([
                        Select::make('from_locale')
                            ->options($allLocales)
                            ->preload()
                            ->searchable()
                            ->required()
                            ->label('Vanaf taal'),
                        Select::make('to_locales')
                            ->options($allLocales)
                            ->preload()
                            ->searchable()
//                        ->default(collect($allLocales)->keys()->toArray())
                            ->required()
                            ->helperText('Zorg dat je niet de taal kiest waar het vandaag vertaald wordt')
                            ->label('Naar talen')
                            ->multiple(),
                    ], []))
                    ->action(function ($record, array $data) {
                        $translations = Translation::where('tag', $record->tag)->whereNotIn('type', ['image', 'repeater'])->get();
                        foreach ($translations as $translation) {
                                if (!$translation->getTranslation('value', $data['from_locale']) && $translation->default) {
                                    $translation->setTranslation('value', $data['from_locale'], $translation->default);
                                    $translation->save();
                                }
                                StartTranslationOfModel::dispatch($translation, $data['from_locale'], $data['to_locales']);
                        }

                        Notification::make()
                            ->title("De tab wordt vertaald")
                            ->success()
                            ->send();
                    }),
            ]);
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
            'edit' => EditTranslation::route('/{record}/edit'),
        ];
    }
}
