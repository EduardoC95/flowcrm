<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Services\FollowUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DealFollowUpController extends Controller
{
    public function cancel(Request $request, Deal $deal, FollowUpService $followUpService): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $followUp = $followUpService->cancelForDeal(
            $deal,
            $validated['cancellation_reason'] ?? 'Cancelado manualmente pelo utilizador',
            $request->user(),
        );

        return back()->with(
            $followUp ? 'success' : 'error',
            $followUp ? 'Follow-up automático cancelado.' : 'Não existe follow-up automático ativo neste negócio.',
        );
    }

    public function markClientReplied(Request $request, Deal $deal, FollowUpService $followUpService): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $followUp = $followUpService->markClientReplied($deal, $request->user());

        return back()->with(
            $followUp ? 'success' : 'error',
            $followUp ? 'Resposta do cliente registada e follow-up automático parado.' : 'Não existe follow-up automático ativo neste negócio.',
        );
    }
}
