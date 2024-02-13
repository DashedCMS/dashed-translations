<?php

namespace Dashed\DashedTranslations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public static function get($name, $tag, $default = null, $type = 'text', $variables = null)
    {
        $tableExists = Cache::remember('dashed__translations_table_exists', 60, function () {
            return Schema::hasTable('dashed__translations');
        });

        if (!$tableExists) {
            return $default;
        }

        if ($name && $default === null) {
            $default = $name;
            $name = Str::slug($name);
        }

        if ($variables) {
            return self::getByParams($name, $tag, $default, $type, $variables);
        }

        $result = Cache::rememberForever(Str::slug($name . $tag . app()->getLocale() . $type), function () use ($name, $tag, $default, $type, $variables) {
            return self::getByParams($name, $tag, $default, $type, $variables);
        });

        return $result;
    }

    public static function getByParams($name, $tag, $default, $type, $variables)
    {
        if ($default === null) {
            $default = $name;
        }

        $translation = self::where('name', $name)->where('tag', $tag)->where('type', $type)->first();
        if (!$translation) {
            $translation = self::withTrashed()->where('name', $name)->where('tag', $tag)->first();
            if (!$translation) {
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
