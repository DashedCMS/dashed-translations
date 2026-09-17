<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Filament\Resources\TranslationStateResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedTranslations\Models\TranslationState;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;

class TranslationStateStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '5s';

    protected function getCards(): array
    {
        $from = TranslationStateUpdater::fromLocale();
        $cards = [];

        foreach (TranslationStateUpdater::toLocales() as $locale) {
            $perStatus = TranslationState::query()
                ->where('from_locale', $from)
                ->where('to_locale', $locale)
                ->selectRaw('status, count(*) as aantal')
                ->groupBy('status')
                ->pluck('aantal', 'status');

            $bezig = AutomatedTranslationProgress::query()
                ->where('to_locale', $locale)
                ->whereNotIn('status', ['finished', 'error'])
                ->count();

            $cards[] = Stat::make(strtoupper($locale), ($perStatus[TranslationState::STATUS_TRANSLATED] ?? 0) . ' ' . __('vertaald'))
                ->description(implode(' · ', [
                    ($perStatus[TranslationState::STATUS_MISSING] ?? 0) . ' ' . __('ontbreekt'),
                    ($perStatus[TranslationState::STATUS_OUTDATED] ?? 0) . ' ' . __('verouderd'),
                    ($perStatus[TranslationState::STATUS_MANUAL] ?? 0) . ' ' . __('handmatig'),
                    $bezig . ' ' . __('bezig'),
                ]))
                ->color(($perStatus[TranslationState::STATUS_MISSING] ?? 0) > 0 ? 'danger' : 'success');
        }

        return $cards;
    }
}
