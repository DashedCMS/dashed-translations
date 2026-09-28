# Changelog

All notable changes to `dashed-translations` will be documented in this file.

## Unreleased

- Sneller: `Translation::get` onthoudt vertalingen per request (scoped `TranslationMemo`, leeg na elke queue-job en elk Octane-request en na opslaan of verwijderen van een vertaling) en kijkt per proces nog maar één keer of de tabel bestaat. Een categoriepagina deed eerder ruim 600 cache-calls alleen voor die check.

## 4.6.1 - 2026-09-24

- Fix: een bestaande vertaling wordt alleen nog hergebruikt als de brontekst byte-exact gelijk is. De tekstkolom vergelijkt zonder hoofdletters en zonder spaties achteraan (`utf8mb4_unicode_ci`), dus een rij voor "Call Credit" bediende ook "Call credit", terwijl het terugschrijven letterlijk vervangt. Zo'n tekst bleef in de brontaal staan tussen wel vertaalde teksten in, zichtbaar als opties van een keuzeveld in verschillende talen door elkaar. Opnieuw vertalen van het betreffende model herstelt de doeltaal.

## 4.6.0 - 2026-09-21

- Nieuw: scherm **Vertaalstatus** (menu Vertalingen, alleen zichtbaar met DeepL aan) met per model en doeltaal de status ontbreekt, vertaald, verouderd of handmatig aangepast, gegroepeerd per soort, met filters op status en taal, een badge Bezig/Fout voor een lopende of mislukte vertaaljob, en rij-, bulk- en twee koptekstacties om opnieuw te vertalen.
- Nieuw: `TranslatableRegistry` waarin pakketten hun vertaalbare modellen aanmelden (`Translatable::make(...)`), tabel `dashed__translation_states` met een vingerafdruk van bron en doel per model, brontaal en doeltaal, en een `saved`/`deleted`-listener die bronwijzigingen markeert.
- Nieuw: nachtelijke ronde `dashed:translate-outdated` (03:00), alleen actief met de instelling "Ontbrekende en verouderde vertalingen 's nachts automatisch bijwerken" aan, met een plafond per nacht (`auto_translate_max_per_run`, standaard 200); handmatig aangepaste vertalingen worden nooit automatisch overschreven.
- Nieuw: `dashed:translation-states:rescan {--baseline}` vergelijkt alle vertaalbare inhoud opnieuw met de laatste stempel en ruimt verweesde statusrijen op (met behoud van rijen die alleen soft-deleted zijn); `--baseline` zet daarna ook de nulmeting in de wachtrij. Een migratie zet die nulmeting bij het uitrollen zelf al in de wachtrij.
- Gewijzigd: de knop "Bulk vertalen" op de instellingenpagina is vervangen door een verwijzing naar het scherm Vertaalstatus.
- Nieuw: `TranslationStarter::start()` vertaalt naast het model ook elk geregistreerd kind dat in de vingerafdruk van de ouder meetelt (formuliervelden, opties van filters en extra's). De rijactie, de bulkactie, de koptekstactie en de nachtronde lopen erdoorheen; zonder dat bleef de status van zo'n ouder op ontbreekt staan terwijl de knop "gestart" meldde.
- Nieuw: de statuskaarten boven het scherm zijn per taal en per status, en een klik zet het status- en taalfilter van de tabel. Zoeken op naam werkt woord voor woord over alle geregistreerde soorten.
- Nieuw: de koptekstactie "Vertaal alles wat ontbreekt of verouderd is" heeft hetzelfde plafond per keer als de nachtronde (`auto_translate_max_per_run`).
- Gewijzigd: alle tellingen en lijsten lezen `TranslationState::scopeInRegister()` in plaats van `whereHasMorph('model', '*')`; een project waar een pakket uit verdwijnt krijgt minder rijen in plaats van een "class not found".
- Gewijzigd: de nulmeting draait als één job per geregistreerd model, los opnieuw te proberen met `queue:retry`.
- Fix: het verwijderen van een kind (een formulierveld bijvoorbeeld) werkt de status van de ouder bij.
- Fix: `ContentFingerprint` slaat een `$translatable`-kolom over die niet in de tabel bestaat, en `ouderVan()` stopt bij de eerste passende registratie in plaats van voor elke registratie een query te doen.
- Vereist na uitrol: `php artisan migrate` (maakt `dashed__translation_states` aan en zet de nulmeting in de wachtrij); de wachtrij moet draaien.

## 4.5.0 - 2026-08-18

- Fix: de door een project ingestelde `ignorableKeysForTranslations` knijpt geen kolommen met gewone inhoud meer leeg. Die lijst beschermt de structuur-sleutels van builder-blokken, maar werd op élke kolom toegepast en matcht op substring. Vrijwel elk project zet er `name` op, dus een repeater die zijn waarden als `[['name' => '...']]` opslaat — de opties van een keuze-veld bijvoorbeeld — leverde geen enkele te vertalen tekst op: de doeltaal bleef leeg en viel terug op de brontaal, ook nadat elke andere kolom al vertaald was.
- Nieuw: een model kan zulke kolommen aanmelden, waarna alleen de vaste structuur-sleutels (`type`, `url`, `icon`, `background`) blijven gelden:

  ```php
  cms()->builder('plainContentColumnsForTranslations', [
      FormField::class => ['options', 'images'],
  ]);
  ```

  De vergelijking gaat via `instanceof`, dus een project dat een Dashed-model uitbreidt erft de registratie van de package. Zonder registratie verandert er niets.

## 4.2.8 - 2026-07-01

- Fix: the first sentence of a translated content block could stay in the source language while the rest of the block translated correctly. A cached translation string that differed from the content by only trailing/leading whitespace was reused on lookup (MySQL's `=` ignores trailing spaces), but the byte-exact `str_replace` write-back in `AutomatedTranslationProgress::recursiveReplace()` then failed to match, so the source text was never overwritten. Whole content nodes are now matched whitespace-insensitively.

## 4.2.5 - 2026-06-03

- Fix: only read a translated `name` in `AutomatedTranslationProgressResource` when the model actually has `name` as a translatable attribute (guard with `isTranslatableAttribute('name')`), avoiding errors on models without a translatable name.
- Chore: tidy import ordering in `DashedTranslationsPlugin` and `AutomatedTranslationStringResource`.

## 4.2.4 - 2026-06-03

- Fix: preserve leading/trailing whitespace around translated strings. DeepL strips edge whitespace, so rich-text nodes split by a mark (e.g. bold) lost the space between them ("you can see the " + **Afzender** rendered as "theAfzender"). `AutomatedTranslation::translate()` now restores the source's exact edge whitespace.

## 4.2.3 - 2026-06-03

- Fix: widen the `error` column on `dashed__automated_translation_progress` from VARCHAR(255) to TEXT. Provider error messages (e.g. DeepL HTTP 403 responses) exceeded 255 chars, so recording a failure crashed with "Data too long for column 'error'" and masked the original error. Run `php artisan migrate` after updating.

## 4.2.2 - 2026-06-03

- Fix: translating builder content no longer throws "Typed property Filament\Schemas\Components\Component::$container must not be accessed before initialization". On Filament v4, `getChildComponents()` lazily builds a Schema that requires a Livewire container, which standalone block definitions don't have. `ExtractStringsToTranslate` and `TranslateValueFromModel` now resolve nested block children via a container-safe `getDefaultChildComponents()`.

## 1.0.0 - 202X-XX-XX

- initial release
