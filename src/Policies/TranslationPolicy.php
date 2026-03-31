<?php

namespace Dashed\DashedTranslations\Policies;

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Policies\BaseResourcePolicy;

class TranslationPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Translation';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('edit_translation');
    }

    public function view(User $user, \Illuminate\Database\Eloquent\Model $record): bool
    {
        return $user->can('edit_translation');
    }

    public function delete(User $user, \Illuminate\Database\Eloquent\Model $record): bool
    {
        return $user->can('edit_translation');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('edit_translation');
    }
}
