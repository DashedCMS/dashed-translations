<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Filament\Resources\TranslationStateResource\Widgets;

use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;
use Dashed\DashedTranslations\Filament\Resources\TranslationStateResource;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Dashed\DashedTranslations\Models\TranslationState;
use Filament\Widgets\Widget;

/**
 * Eén regel per doeltaal met per status een klikbaar cijfer dat het status-
 * en taalfilter van de tabel zet. Eerder was dit één Stat-kaart per
 * (taal, status); met vijf statussen plus "Bezig" en tien doeltalen waren
 * dat zestig kaarten in een raster van drie breed, waardoor de talen door
 * elkaar liepen. "Bezig" krijgt geen url: dat is geen kolomwaarde van
 * status maar een live afgeleide van AutomatedTranslationProgress.
 */
class TranslationStateStats extends Widget
{
    protected string $view = 'dashed-translations::widgets.translation-state-stats';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '5s';

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    /** @return array<string, array{label: string, color: string}> */
    public static function statussen(): array
    {
        return [
            TranslationState::STATUS_MISSING => ['label' => __('Ontbreekt'), 'color' => 'danger'],
            TranslationState::STATUS_OUTDATED => ['label' => __('Verouderd'), 'color' => 'warning'],
            TranslationState::STATUS_MANUAL => ['label' => __('Handmatig aangepast'), 'color' => 'info'],
            TranslationState::STATUS_TRANSLATED => ['label' => __('Vertaald'), 'color' => 'success'],
            TranslationState::STATUS_EXCLUDED => ['label' => __('Niet vertalen'), 'color' => 'gray'],
        ];
    }

    /**
     * @return list<array{locale: string, cells: list<array{status: string, count: int, color: string, url: string}>, running: int}>
     */
    public function getRows(): array
    {
        $from = TranslationStateUpdater::fromLocale();
        $rows = [];

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

            $cells = [];

            foreach (self::statussen() as $status => $meta) {
                $count = (int) ($perStatus[$status] ?? 0);

                $cells[] = [
                    'status' => $status,
                    'count' => $count,
                    'color' => $count > 0 ? $meta['color'] : 'gray',
                    'url' => ResourceFilterUrl::for(TranslationStateResource::class, [
                        'status' => ['values' => [$status]],
                        'to_locale' => ['values' => [$locale]],
                    ]),
                ];
            }

            $rows[] = [
                'locale' => strtoupper($locale),
                'cells' => $cells,
                'running' => AutomatedTranslationProgress::query()
                    ->where('to_locale', $locale)
                    ->whereNotIn('status', ['finished', 'error'])
                    ->count(),
            ];
        }

        return $rows;
    }
}
