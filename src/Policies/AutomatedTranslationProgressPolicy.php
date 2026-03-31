<?php

namespace Dashed\DashedTranslations\Policies;

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Policies\BaseResourcePolicy;

class AutomatedTranslationProgressPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'AutomatedTranslationProgress';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('edit_automated_translation_progress');
    }

    public function view(User $user, \Illuminate\Database\Eloquent\Model $record): bool
    {
        return $user->can('edit_automated_translation_progress');
    }

    public function delete(User $user, \Illuminate\Database\Eloquent\Model $record): bool
    {
        return $user->can('edit_automated_translation_progress');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('edit_automated_translation_progress');
    }
}
