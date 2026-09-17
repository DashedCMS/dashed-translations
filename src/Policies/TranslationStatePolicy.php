<?php

namespace Dashed\DashedTranslations\Policies;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Policies\BaseResourcePolicy;

class TranslationStatePolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'TranslationState';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('edit_automated_translation_progress');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('edit_automated_translation_progress');
    }
}
