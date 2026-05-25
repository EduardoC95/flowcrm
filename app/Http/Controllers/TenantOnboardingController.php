<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantOnboardingController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->current_tenant_id && $request->user()->belongsToTenant($request->user()->current_tenant_id)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('tenants/Onboarding');
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        $request->user()->tenants()->attach($tenant->id, [
            'role' => Tenant::ROLE_OWNER,
        ]);

        $request->user()->forceFill([
            'current_tenant_id' => $tenant->id,
        ])->save();

        $logger->log(
            'created',
            'tenants',
            $tenant->id,
            $tenant,
            'Tenant created during onboarding.',
        );

        return redirect()->route('dashboard');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $counter = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
