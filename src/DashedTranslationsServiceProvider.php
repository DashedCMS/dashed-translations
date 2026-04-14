<?php

namespace Dashed\DashedTranslations;

use Livewire\Livewire;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedTranslations\Commands\RemoveUnusedTranslations;
use Dashed\DashedTranslations\Filament\Pages\Settings\TranslationsSettingsPage;
use Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource\Pages\Widgets\AutomatedTranslationStats;

class DashedTranslationsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-translations';

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        cms()->registerSettingsPage(TranslationsSettingsPage::class, 'Vertalingen', 'language', 'Instellingen voor AI vertalingen');

        $package
            ->name('dashed-translations')
            ->hasCommands([
                RemoveUnusedTranslations::class,
            ])
            ->hasViews();

        cms()->builder('plugins', [
            new DashedTranslationsPlugin(),
        ]);
    }

    public function bootingPackage()
    {
        Livewire::component('automated-translation-stats', AutomatedTranslationStats::class);

        Gate::policy(\Dashed\DashedTranslations\Models\Translation::class, \Dashed\DashedTranslations\Policies\TranslationPolicy::class);
        Gate::policy(\Dashed\DashedTranslations\Models\AutomatedTranslationProgress::class, \Dashed\DashedTranslations\Policies\AutomatedTranslationProgressPolicy::class);

        cms()->registerRolePermissions('Vertalingen', [
            'edit_translation' => 'Vertalingen bewerken',
            'edit_automated_translation_progress' => 'Vertalingsvoortgang bewerken',
        ]);

        cms()->registerResourceDocs(
            resource: \Dashed\DashedTranslations\Filament\Resources\TranslationResource::class,
            title: 'Vertalingen',
            intro: 'Hier beheer je alle vaste teksten van het CMS en de website per taal. Van knoppen tot foutmeldingen en standaard zinnen, alles pas je hier in eigen woorden aan zonder dat je iets in de code hoeft te doen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Vertalingen per taal opzoeken en aanpassen.
- Nieuwe vertalingen toevoegen voor een specifieke tekst.
- Zoeken op een tag of een stuk tekst om snel de juiste regel te vinden.
- Teksten terugzetten naar de oorspronkelijke waarde als je er niet tevreden over bent.
MARKDOWN,
                ],
                [
                    'heading' => 'Automatisch vertalen',
                    'body' => 'Is DeepL gekoppeld? Dan kun je teksten automatisch laten vertalen vanuit je hoofdtaal naar elke andere taal. Dat scheelt veel typewerk als je met meerdere talen werkt. De automatisch vertaalde teksten kun je altijd zelf nog bijschaven als je een net iets andere formulering wilt.',
                ],
                [
                    'heading' => 'Bulk vertalen met tags',
                    'body' => 'Vertalingen zijn ingedeeld op tags, bijvoorbeeld een tag voor de checkout of voor de e-mails. Met de actie "Vertaal tag" pak je in een keer alle vertalingen binnen een tag en laat je ze doorvertalen naar andere talen.',
                ],
            ],
            tips: [
                'Gebruik de zoekfunctie om een vertaling snel terug te vinden, de lijst kan lang worden.',
                'Controleer automatische vertalingen altijd even, soms klopt de toon of context niet helemaal.',
                'Werk per tag wanneer je een nieuwe taal invoert, dat houdt het overzichtelijk.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedTranslations\Filament\Resources\AutomatedTranslationProgressResource::class,
            title: 'Automatische vertalingen',
            intro: 'Dit overzicht laat zien hoe het met je automatische vertaal jobs staat. Je ziet per job of hij nog in behandeling is, al klaar is of mislukt, en je kunt mislukte vertalingen opnieuw aanbieden.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Zien welke vertaal jobs openstaan, afgerond zijn of misgegaan zijn.
- Details van een job openen om te zien wat er precies vertaald is.
- Een mislukte job opnieuw starten zodat hij een tweede kans krijgt.
- Meerdere jobs tegelijk opnieuw aanbieden via een bulk actie.
MARKDOWN,
                ],
                [
                    'heading' => 'Live voortgang volgen',
                    'body' => 'De pagina ververst zichzelf elke vijf seconden automatisch, zodat je altijd de actuele status ziet zonder te hoeven klikken. Zo weet je meteen wanneer een vertaling klaar is en kun je aan de slag met controleren en publiceren.',
                ],
            ],
            tips: [
                'Wacht rustig even, grote vertaalklussen kunnen een paar minuten duren.',
                'Komt een job twee keer met een fout terug? Controleer dan de koppeling met de vertaalprovider.',
                'Ruim oude afgeronde jobs af en toe op om het overzicht schoon te houden.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedTranslations\Filament\Pages\Settings\TranslationsSettingsPage::class,
            title: 'Vertalingen instellingen',
            intro: 'Met deze pagina zet je automatische vertalingen via DeepL aan of uit. DeepL vertaalt teksten razendsnel en met goede kwaliteit, ideaal als je content in meerdere talen wil aanbieden. Let op: deze functie is nog in beta, controleer vertalingen altijd voordat je ze publiceert.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => 'Je activeert DeepL en vult je API sleutel in. Bovenaan deze pagina vind je een knop om alle bestaande teksten in een keer te laten vertalen via een bulk actie.',
                ],
                [
                    'heading' => 'Hoe haal je je DeepL API sleutel op?',
                    'body' => <<<MARKDOWN
1. Ga naar [deepl.com/pro-api](https://www.deepl.com/pro-api) en maak een account aan.
2. Kies een DeepL API abonnement dat past bij het volume dat je verwacht (er is ook een gratis variant).
3. Log in op je DeepL account en ga naar **Account > Authentication Key for DeepL API**.
4. Kopieer de sleutel die je daar ziet staan.
5. Zet hieronder DeepL aan en plak de sleutel in het API key veld.
6. Sla op en test de bulk vertaal actie op een enkel item om te zien of het werkt.
MARKDOWN,
                ],
            ],
            fields: [
                'DeepL actief' => 'Aan zet de DeepL koppeling actief, waarna je teksten automatisch kunt laten vertalen. Uit betekent dat alle vertalingen handmatig moeten gebeuren.',
                'DeepL API sleutel' => 'De authenticatie sleutel van je DeepL account. Deze vind je in je DeepL account onder Account, bij Authentication Key for DeepL API. Het veld verschijnt zodra je DeepL hebt aangezet.',
            ],
            tips: [
                'DeepL vertalingen zijn beta. Loop ze altijd zelf na voor je iets publiceert, vooral bij merknamen en vakjargon.',
                'De gratis DeepL variant heeft een maandelijks tekenlimiet. Houd dat in de gaten als je grote hoeveelheden tekst vertaalt.',
                'Gebruik de bulk vertaal knop bovenaan deze pagina om bestaande content in een keer mee te nemen.',
            ],
        );
    }
}
