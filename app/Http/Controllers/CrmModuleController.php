<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmModuleController extends Controller
{
    public function __invoke(Request $request, string $module): Response
    {
        abort_unless(in_array($module, ['entities', 'people', 'calendar', 'deals'], true), 404);

        return Inertia::render('crm/Placeholder', [
            'module' => $module,
            'tenant' => $request->user()->currentTenant?->only(['id', 'name', 'slug']),
        ]);
    }
}
