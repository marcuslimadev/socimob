<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LeadCustomerService
{
    public function ensureClientForLead(Lead $lead): ?User
    {
        if ($lead->user_id) {
            $user = User::find($lead->user_id);
            if ($user && $this->isPlaceholderEmail($user->email) && !empty($lead->email)) {
                $user->update(['email' => $lead->email]);
            }
            return $user;
        }

        $email = $lead->email;

        $user = null;
        if (!empty($email)) {
            $user = User::where('email', $email)->first();
        }
        if ($user) {
            if ($user->tenant_id === null && $lead->tenant_id) {
                $user->update(['tenant_id' => $lead->tenant_id]);
            }
            if (!$lead->user_id) {
                $lead->update(['user_id' => $user->id]);
            }
            return $user;
        }

        if (empty($email)) {
            $email = $this->buildPlaceholderEmail($lead);
        }

        $user = User::create([
            'name' => $lead->nome ?: ($lead->whatsapp_name ?: 'Cliente'),
            'email' => $email,
            'password' => Hash::make(Str::random(16)),
            'role' => 'client',
            'is_active' => 1,
            'tenant_id' => $lead->tenant_id,
        ]);

        $lead->update(['user_id' => $user->id]);

        return $user;
    }

    private function buildPlaceholderEmail(Lead $lead): string
    {
        $tenantPart = $lead->tenant_id ?: 0;
        $base = 'lead-' . $tenantPart . '-' . $lead->id;
        $email = $base . '@no-email.local';

        $suffix = 1;
        while (User::where('email', $email)->exists()) {
            $email = $base . '-' . $suffix . '@no-email.local';
            $suffix++;
        }

        return $email;
    }

    private function isPlaceholderEmail(?string $email): bool
    {
        return $email && str_ends_with($email, '@no-email.local');
    }
}
