<?php

namespace App\Policies;

use App\Models\CrmConversation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmConversationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id === request()->route("tenant")->id;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CrmConversation $crmConversation): bool
    {
        return $user->tenant_id === $crmConversation->tenant_id &&
               ($user->id === $crmConversation->assigned_user_id || $user->hasPermission("view_all_conversations"));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id === request()->route("tenant")->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CrmConversation $crmConversation): bool
    {
        return $user->tenant_id === $crmConversation->tenant_id &&
               ($user->id === $crmConversation->assigned_user_id || $user->hasPermission("edit_all_conversations"));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CrmConversation $crmConversation): bool
    {
        return $user->tenant_id === $crmConversation->tenant_id &&
               $user->hasPermission("delete_conversations");
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CrmConversation $crmConversation): bool
    {
        return $user->tenant_id === $crmConversation->tenant_id &&
               $user->hasPermission("restore_conversations");
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CrmConversation $crmConversation): bool
    {
        return $user->tenant_id === $crmConversation->tenant_id &&
               $user->hasPermission("force_delete_conversations");
    }
}
