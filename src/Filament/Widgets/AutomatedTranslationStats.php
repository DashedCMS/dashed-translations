<?php

namespace Dashed\DashedTranslations\Filament\Widgets;

use Dashed\DashedTranslations\Classes\AutomatedTranslation;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Filament\Widgets\StatsOverviewWidget;
use Flowframe\Trend\Trend;
use Filament\Widgets\ChartWidget;
use Dashed\DashedCore\Models\NotFoundPageOccurrence;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class AutomatedTranslationStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Statistieken';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = '1s';

    protected function getCards(): array
    {
        $totalTranslatedStrings = AutomatedTranslationProgress::sum('total_strings_translated');

        return [
            StatsOverviewWidget\Stat::make('Totaal vertalingen', AutomatedTranslationProgress::count()),
            StatsOverviewWidget\Stat::make('Voltooide vertalingen', AutomatedTranslationProgress::where('status', 'finished')->count()),
            StatsOverviewWidget\Stat::make('Pending vertalingen', AutomatedTranslationProgress::where('status', 'pending')->count()),
            StatsOverviewWidget\Stat::make('Opnieuw proberen vertalingen', AutomatedTranslationProgress::where('status', 'retrying')->count()),
            StatsOverviewWidget\Stat::make('Foute vertalingen', AutomatedTranslationProgress::where('status', 'error')->count()),
            StatsOverviewWidget\Stat::make('Zinnen om nog te vertalen', AutomatedTranslationProgress::sum('total_strings_to_translate') - $totalTranslatedStrings),
            StatsOverviewWidget\Stat::make('Vertaalde zinnen', $totalTranslatedStrings),
        ];
    }
}
