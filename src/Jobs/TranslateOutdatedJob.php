<?php

declare(strict_types=1);

namespace Dashed\DashedTranslations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedTranslations\Models\TranslationState;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStarter;
use Dashed\DashedTranslations\Classes\Translatables\TranslationStateUpdater;

/**
 * Zet voor elk item dat vertaling nodig heeft één translateModel()-aanroep
 * klaar, met alle doeltalen van dat item gebundeld. Wordt door het scherm
 * (met of zonder handmatig aangepaste items, zonder plafond) en door de
 * nachtelijke ronde (zonder handwerk, met plafond) gebruikt; zo doen knop
 * en cron precies hetzelfde.
 */
class TranslateOutdatedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  array<int, string>  $toLocales
     * @param  array<int, int>|null  $stateIds  Alleen deze statusrijen (selectie in het scherm)
     */
    public function __construct(
        public array $toLocales,
        public bool $includeManual = false,
        public ?int $limit = null,
        public ?array $stateIds = null,
    ) {
    }

    public function handle(): void
    {
        $from = TranslationStateUpdater::fromLocale();
        $statuses = [TranslationState::STATUS_MISSING, TranslationState::STATUS_OUTDATED];

        if ($this->includeManual) {
            $statuses[] = TranslationState::STATUS_MANUAL;
        }

        $query = TranslationState::query()
            // Dezelfde afbakening als het scherm en de statswidget: alleen
            // soorten die nu geregistreerd staan. Zonder dit vreten rijen van
            // een verwijderd pakket het plafond op zonder dat er iets vertaald
            // wordt, en telt de bevestigingsmodal iets anders dan de knop doet.
            ->inRegister()
            ->where('from_locale', $from)
            ->whereIn('to_locale', $this->toLocales)
            ->whereIn('status', $statuses)
            ->orderBy('checked_at')
            ->orderBy('id');

        if ($this->stateIds !== null) {
            $query->whereIn('id', $this->stateIds);
        }

        // Per model bundelen, in volgorde van het langst niet bekeken.
        $perModel = [];

        foreach ($query->cursor() as $state) {
            $key = $state->model_type . '|' . $state->model_id;

            if (! isset($perModel[$key])) {
                if ($this->limit !== null && count($perModel) >= $this->limit) {
                    continue;
                }

                $perModel[$key] = ['state' => $state, 'locales' => []];
            }

            $perModel[$key]['locales'][] = $state->to_locale;
        }

        foreach ($perModel as $item) {
            $model = $item['state']->model;

            if (! $model) {
                continue;
            }

            $loopt = AutomatedTranslationProgress::query()
                ->where('model_type', $model::class)
                ->where('model_id', $model->getKey())
                ->whereNotIn('status', ['finished', 'error'])
                ->exists();

            if ($loopt) {
                continue;
            }

            // Via TranslationStarter en niet rechtstreeks via translateModel():
            // die laatste stuurt alleen het model, zijn metadata en zijn
            // customBlocks weg, en laat geregistreerde kinderen als
            // Form->fields onvertaald. Zie TranslationStarter.
            TranslationStarter::start($model, $from, array_values(array_unique($item['locales'])));
        }
    }
}
