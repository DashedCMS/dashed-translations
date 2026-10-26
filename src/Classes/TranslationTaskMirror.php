<?php

namespace Dashed\DashedTranslations\Classes;

use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\BackgroundTask;
use Dashed\DashedTranslations\Models\AutomatedTranslationProgress;

/**
 * Spiegelt de lopende vertalingen naar één taak in het takenicoon van
 * dashed-core. Eén taak voor alles wat er loopt, en niet een per
 * progress-rij: een bulkvertaling van vijfhonderd producten naar twee talen
 * zijn duizend rijen, en die horen niet als duizend taken in de topbalk.
 *
 * De taak telt de zinnen van elke rij die nog loopt plus elke rij die sinds
 * de start van de taak is bijgewerkt, zodat de balk niet terugloopt als er
 * rijen klaar zijn. Zonder starter: de rijen worden in de wachtrij
 * aangemaakt en kennen geen gebruiker.
 */
class TranslationTaskMirror
{
    public const TYPE = 'translations';

    public const OPEN_STATUSES = ['pending', 'in_progress', 'retrying'];

    /**
     * Na elke opgeslagen progress-rij, hooguit eens per vijf seconden: een
     * vertaling slaat de rij op na elke zin. De staart vangt de planner op
     * (elke minuut sync()).
     */
    public static function touched(): void
    {
        if (! class_exists(BackgroundTask::class)) {
            return;
        }

        if (rescue(fn () => Cache::add('dashed-translations:task-mirror', true, 5), false, report: false)) {
            self::sync();
        }
    }

    public static function sync(): ?BackgroundTask
    {
        if (! class_exists(BackgroundTask::class)) {
            return null;
        }

        return rescue(function () {
            $task = BackgroundTask::query()
                ->where('type', self::TYPE)
                ->active()
                ->latest('id')
                ->first();

            $open = AutomatedTranslationProgress::query()->whereIn('status', self::OPEN_STATUSES)->count();

            if (! $task && $open === 0) {
                return null;
            }

            $task ??= BackgroundTask::begin(self::TYPE, __('Vertalen'), notify: false);

            $rows = AutomatedTranslationProgress::query()
                ->where(fn ($query) => $query
                    ->whereIn('status', self::OPEN_STATUSES)
                    ->orWhere('updated_at', '>=', $task->started_at));

            $total = (int) (clone $rows)->sum('total_strings_to_translate');
            $done = (int) (clone $rows)->sum('total_strings_translated');

            if ($open > 0) {
                return $task->progress(min($done, $total), max($total, 1));
            }

            $models = (clone $rows)->count();
            $errors = (clone $rows)->where('status', 'error')->count();
            $message = __(':aantal vertalingen klaar', ['aantal' => $models - $errors]);

            if ($errors > 0) {
                $message .= ', '.__(':aantal mislukt', ['aantal' => $errors]);
            }

            return $task->finish($message.'.');
        }, null, report: true);
    }
}
