<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityLogger
{
    public function logLogin(Agent $agent): void
    {
        Log::channel('security')->info('LOGIN', [
            'agent_id'       => $agent->id,
            'cooperative_id' => $agent->cooperative_id,
            'at'             => now()->toIso8601String(),
        ]);
        // NE PAS logger : email, nom, prénom, password, token
    }

    public function log403(Request $request, string $code): void
    {
        Log::channel('security')->warning('ACCESS_DENIED', [
            'code'     => $code,
            'path'     => $request->path(),
            'agent_id' => $request->user()?->id,
            'at'       => now()->toIso8601String(),
        ]);
        // NE PAS logger : IP nominative, contenu de la requête
    }

    public function logRoleChange(Agent $agent, string $oldRole, string $newRole): void
    {
        Log::channel('security')->info('ROLE_CHANGE', [
            'agent_id' => $agent->id,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'at'       => now()->toIso8601String(),
        ]);
    }
}
