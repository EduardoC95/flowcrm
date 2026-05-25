<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $tenantIds = $user->tenants()->pluck('tenants.id');

        if ($tenantIds->isEmpty()) {
            return redirect()->route('tenant.onboarding');
        }

        if (! $user->current_tenant_id) {
            $user->forceFill(['current_tenant_id' => $tenantIds->first()])->save();
            $user->setRelation('currentTenant', $user->tenants()->whereKey($tenantIds->first())->first());

            return $next($request);
        }

        if (! $tenantIds->contains($user->current_tenant_id)) {
            abort(403, 'The selected tenant does not belong to the authenticated user.');
        }

        return $next($request);
    }
}
