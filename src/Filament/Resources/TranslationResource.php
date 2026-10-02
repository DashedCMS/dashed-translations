<?php

namespace Dashed\DashedTranslations\Filament\Resources;

use BackedEnum;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource\Pages\EditTranslation;
use Dashed\DashedTranslations\Filament\Resources\TranslationResource\Pages\ListTranslations;
use Dashed\DashedTranslations\Jobs\StartTranslationOfModel;
use Dashed\DashedTranslations\Models\Translation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TranslationResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;

    protected static ?string $model = Translation::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-language';
    protected static string|UnitEnum|null $navigationGroup = 'Vertalingen';
    protected static ?int $navigationSort = 10;
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
                ->formatStateUsing(fn ($state) => str($state)->headline()->ucfirst())
                ->searchable(query: function ($query, string $search) {

                    $search = mb_strtolower($search);

                    return $query->where(function ($q) use ($search) {

                        $q->where('dashed__translations.tag', 'like', "%{$search}%")

                            ->orWhereExists(function ($sub) use ($search) {
                                $sub->selectRaw('1')
                                    ->from('dashed__translations as s')
                                    ->whereColumn('s.tag', 'dashed__translations.tag')
                                    ->whereNull('s.deleted_at')
                                    ->where(function ($inner) use ($search) {
                                        $inner->whereRaw('LOWER(s.name) LIKE ?', ["%{$search}%"])
                                            ->orWhereRaw('LOWER(s.tag) LIKE ?', ["%{$search}%"])
                                            ->orWhereRaw('LOWER(s.default) LIKE ?', ["%{$search}%"])
                                            ->orWhereRaw('LOWER(s.type) LIKE ?', ["%{$search}%"])
                                            ->orWhereRaw('LOWER(CAST(s.value AS CHAR)) LIKE ?', ["%{$search}%"]);
                                    });
                            });

                    });
                })
                ->sortable(),
        ])
            ->recordActions([
                EditAction::make('edit'),
                Action::make('translateTab')
                    ->button()
                    ->icon('heroicon-m-language')
                    ->label(__('Vertaal tab'))
                    ->visible(AutomatedTranslation::automatedTranslationsEnabled())
                    ->schema(array_merge([
                        Select::make('from_locale')
                            ->options($allLocales)
                            ->preload()
                            ->searchable()
                            ->required()
                            ->label(__('Vanaf taal')),
                        Select::make('to_locales')
                            ->options($allLocales)
                            ->preload()
                            ->searchable()
//                        ->default(collect($allLocales)->keys()->toArray())
                            ->required()
                            ->helperText(__('Zorg dat je niet de taal kiest waar het vandaag vertaald wordt'))
                            ->label(__('Naar talen'))
                            ->multiple(),
                    ], []))
                    ->action(function ($record, array $data) {
                        $translations = Translation::where('tag', $record->tag)->whereNotIn('type', ['image', 'repeater'])->get();
                        foreach ($translations as $translation) {
                            if (! $translation->getTranslation('value', $data['from_locale']) && $translation->default) {
                                $translation->setTranslation('value', $data['from_locale'], $translation->default);
                                $translation->save();
                            }
                            StartTranslationOfModel::dispatch($translation, $data['from_locale'], $data['to_locales']);
                        }

                        Notification::make()
                            ->title(__("De tab wordt vertaald"))
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
