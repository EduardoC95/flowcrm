<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Services\DealTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DealTimelineController extends Controller
{
    public function index(Request $request, Deal $deal, DealTimelineService $timeline): JsonResponse
    {
        Gate::authorize('view', $deal);

        $filters = $request->validate([
            'type' => ['nullable', 'in:all,email,activity,note,proposal,follow_up,change,product,system'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'items' => $timeline->forDeal($deal, $filters),
        ]);
    }
}
