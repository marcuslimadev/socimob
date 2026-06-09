<?php

namespace App\Policies;

use App\Models\CrmConversationTask;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CrmTaskPolicy
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
    public function view(User $user, CrmConversationTask $crmConversationTask): bool
    {
        return $user->tenant_id === $crmConversationTask->tenant_id &&
               ($user->id === $crmConversationTask->assigned_user_id || $user->hasPermission("view_all_tasks"));
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
    public function update(User $user, CrmConversationTask $crmConversationTask): bool
    {
        return $user->tenant_id === $crmConversationTask->tenant_id &&
               ($user->id === $crmConversationTask->assigned_user_id || $user->hasPermission("edit_all_tasks"));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CrmConversationTask $crmConversationTask): bool
    {
        return $user->tenant_id === $crmConversationTask->tenant_id &&
               $user->hasPermission("delete_tasks");
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CrmConversationTask $crmConversationTask): bool
    {
        return $user->tenant_id === $crmConversationTask->tenant_id &&
               $user->hasPermission("restore_tasks");
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CrmConversationTask $crmConversationTask): bool
    {
        return $user->tenant_id === $crmConversationTask->tenant_id &&
               $user->hasPermission("force_delete_tasks");
    }
}
