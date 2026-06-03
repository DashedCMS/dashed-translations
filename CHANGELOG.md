# Changelog

All notable changes to `dashed-translations` will be documented in this file.

## 4.2.4 - 2026-06-03

- Fix: preserve leading/trailing whitespace around translated strings. DeepL strips edge whitespace, so rich-text nodes split by a mark (e.g. bold) lost the space between them ("you can see the " + **Afzender** rendered as "theAfzender"). `AutomatedTranslation::translate()` now restores the source's exact edge whitespace.

## 4.2.3 - 2026-06-03

- Fix: widen the `error` column on `dashed__automated_translation_progress` from VARCHAR(255) to TEXT. Provider error messages (e.g. DeepL HTTP 403 responses) exceeded 255 chars, so recording a failure crashed with "Data too long for column 'error'" and masked the original error. Run `php artisan migrate` after updating.

## 4.2.2 - 2026-06-03

- Fix: translating builder content no longer throws "Typed property Filament\Schemas\Components\Component::$container must not be accessed before initialization". On Filament v4, `getChildComponents()` lazily builds a Schema that requires a Livewire container, which standalone block definitions don't have. `ExtractStringsToTranslate` and `TranslateValueFromModel` now resolve nested block children via a container-safe `getDefaultChildComponents()`.

## 1.0.0 - 202X-XX-XX

- initial release
