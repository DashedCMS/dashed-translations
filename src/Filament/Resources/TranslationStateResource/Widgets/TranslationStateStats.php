<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Filament\Resources\TranslationStateResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedTranslations\Models\TranslationState;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Dashed\DashedTranslations\Filament\Resources\TranslationStateResource;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;

/**
 * Eén kaart per (doeltaal, status): de spec vraagt dat klikken op een cijfer
 * het status- en taalfilter van de tabel zet ("Klikken op een cijfer zet het
 * status- en taalfilter van de tabel"). Filament's Stat heeft maar één url()
 * per kaart, dus dat cijfer moet zijn eigen kaart zijn; een enkele kaart per
 * taal met vier cijfers in de omschrijving kan dat niet, want die vier delen
 * één url. "Bezig" krijgt geen url: dat is geen kolomwaarde van status (de
 * SelectFilter kent alleen missing/translated/outdated/manual), maar een
 * live afgeleide van AutomatedTranslationProgress.
 */
class TranslationStateStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '5s';

    protected function getCards(): array
    {
        $from = TranslationStateUpdater::fromLocale();
        $cards = [];

        $statussen = [
            TranslationState::STATUS_MISSING => ['label' => __('Ontbreekt'), 'color' => 'danger'],
            TranslationState::STATUS_OUTDATED => ['label' => __('Verouderd'), 'color' => 'warning'],
            TranslationState::STATUS_MANUAL => ['label' => __('Handmatig aangepast'), 'color' => 'info'],
            TranslationState::STATUS_TRANSLATED => ['label' => __('Vertaald'), 'color' => 'success'],
        ];

        foreach (TranslationStateUpdater::toLocales() as $locale) {
            // inRegister(): dezelfde afbakening als de tabel en de
            // koptekstactie, anders telt de widget rijen van een pakket dat
            // niet meer geregistreerd staat, en klopt het cijfer hier niet
            // meer met wat de tabel toont of de knop oppakt.
            $perStatus = TranslationState::query()
                ->inRegister()
                ->where('from_locale', $from)
                ->where('to_locale', $locale)
                ->selectRaw('status, count(*) as aantal')
                ->groupBy('status')
                ->pluck('aantal', 'status');

            foreach ($statussen as $status => $meta) {
                $aantal = (int) ($perStatus[$status] ?? 0);

                $cards[] = Stat::make(strtoupper($locale) . ' · ' . $meta['label'], (string) $aantal)
                    ->color($aantal > 0 ? $meta['color'] : 'gray')
                    ->url(ResourceFilterUrl::for(TranslationStateResource::class, [
                        'status' => ['values' => [$status]],
                        'to_locale' => ['values' => [$locale]],
                    ]));
            }

            $bezig = AutomatedTranslationProgress::query()
                ->where('to_locale', $locale)
                ->whereNotIn('status', ['finished', 'error'])
                ->count();

            $cards[] = Stat::make(strtoupper($locale) . ' · ' . __('Bezig'), (string) $bezig)
                ->color($bezig > 0 ? 'info' : 'gray');
        }

        return $cards;
    }
}
