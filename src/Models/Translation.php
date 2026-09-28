<?php

namespace Dashed\DashedTranslations\Models;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedTranslations\Classes\TranslationMemo;
use Dashed\DashedCore\Classes\Caching\CacheInvalidator;

class Translation extends Model
{
    use SoftDeletes;
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'tag',
        'name',
        'value',
        'default',
        'type',
        'variables',
    ];

    public $translatable = [
        'value',
    ];

    protected $casts = [
        'variables' => 'array',
    ];

    protected $table = 'dashed__translations';

    /** Alleen true wordt onthouden, zodat migrate en tests in één proces blijven werken. */
    protected static ?bool $tableExists = null;

    protected static function booted(): void
    {
        static::saved(function ($translation) {
            app(TranslationMemo::class)->flush();

            if (class_exists(CacheInvalidator::class)) {
                foreach (Sites::getSites() as $site) {
                    CacheInvalidator::flushSite($site['id']);
                }
            }
        });

        static::deleted(function ($translation) {
            app(TranslationMemo::class)->flush();

            if (class_exists(CacheInvalidator::class)) {
                foreach (Sites::getSites() as $site) {
                    CacheInvalidator::flushSite($site['id']);
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public static function get($name, $tag, $default = null, $type = 'text', $variables = null)
    {
        if (! static::tableExists()) {
            return $default;
        }

        if ($name && $default === null) {
            $default = $name;
            $name = Str::slug($name);
        }

        $cacheKey = Str::slug($name . $tag . app()->getLocale() . $type);
        $memo = app(TranslationMemo::class);

        if ($variables) {
            // De memo bewaart de tekst zonder ingevulde variabelen; invullen
            // gebeurt per aanroep.
            $result = $memo->remember($cacheKey, fn () => Cache::rememberForever($cacheKey, function () use ($name, $tag, $default, $type) {
                return self::getByParams($name, $tag, $default, $type, []);
            }));
            foreach ($variables as $key => $variable) {
                if (is_array($result)) {
                    $result = cms()->convertToHtml($result);
                }
                $result = str_replace(':' . $key . ':', $variable, $result);
            }

            return $result;
        }

        return $memo->remember($cacheKey, fn () => Cache::rememberForever($cacheKey, function () use ($name, $tag, $default, $type, $variables) {
            return self::getByParams($name, $tag, $default, $type, $variables);
        }));
    }

    public static function tableExists(): bool
    {
        if (static::$tableExists) {
            return true;
        }

        $exists = (bool) Cache::remember('dashed__translations_table_exists', 60, function () {
            return Schema::hasTable('dashed__translations');
        });

        if ($exists) {
            static::$tableExists = true;
        }

        return $exists;
    }

    /**
     * Voor tests: reset de procesbrede vlag, zodat een volgende test (of een
     * volgend migrate-commando) de tabel opnieuw controleert in plaats van
     * een verouderd antwoord te hergebruiken.
     */
    public static function forgetTableExists(): void
    {
        static::$tableExists = null;
    }

    public static function getByParams($name, $tag, $default, $type, $variables)
    {
        if ($default === null) {
            $default = $name;
        }

        $translation = self::where('name', $name)->where('tag', $tag)->where('type', $type)->first();
        if (! $translation) {
            $translation = self::withTrashed()->where('name', $name)->where('tag', $tag)->first();
            if (! $translation) {
                $translation = self::updateOrCreate(
                    ['name' => $name, 'tag' => $tag],
                    ['default' => $default, 'type' => $type, 'variables' => $variables]
                );

                if ($variables) {
                    foreach ($variables as $key => $variable) {
                        $default = str_replace(':' . $key . ':', $variable, $default);
                    }
                }

                return $default == $name ? '' : $default;
            } else {
                $translation->restore();
            }
        }

        if ($translation && $translation->type != $type) {
            $translation->type = $type;
            $translation->save();
        }

        if ($translation && $default && $translation->default != $default && $default != $name) {
            $translation->default = $default;
            $translation->save();
        }

        if ($translation && $translation->value) {
            if ($variables) {
                foreach ($variables as $key => $variable) {
                    if (is_array($translation->value)) {
                        $translation->value = cms()->convertToHtml($translation->value);
                    }
                    $translation->value = str_replace(':' . $key . ':', $variable, $translation->value);
                }
            }

            return $translation->value;
        } else {
            if ($translation->default) {
                if ($variables) {
                    foreach ($variables as $key => $variable) {
                        $translation->default = str_replace(':' . $key . ':', $variable, $translation->default);
                    }
                }

                return $translation->default;
            } else {
                if ($variables) {
                    foreach ($variables as $key => $variable) {
                        $default = str_replace(':' . $key . ':', $variable, $default);
                    }
                }

                return $default == $name ? '' : $default;
            }
        }
    }
}
