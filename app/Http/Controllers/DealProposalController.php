<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendDealProposalRequest;
use App\Http\Requests\StoreDealProposalRequest;
use App\Mail\DealProposalMail;
use App\Models\Deal;
use App\Models\DealProposal;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DealProposalController extends Controller
{
    public function store(StoreDealProposalRequest $request, Deal $deal, ActivityLogger $logger): RedirectResponse
    {
        $file = $request->file('proposal');
        $path = $file->store('deal-proposals', 'local');

        $proposal = DealProposal::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'status' => DealProposal::STATUS_DRAFT,
        ]);

        $this->logProposalAction($logger, 'deal_proposal.uploaded', $deal, $proposal, 'Deal proposal uploaded.');

        return back()->with('success', 'Proposta adicionada com sucesso.');
    }

    public function previewEmail(Deal $deal, DealProposal $proposal): JsonResponse
    {
        $this->authorizeProposalForDeal($deal, $proposal, 'view');

        return response()->json($this->emailDefaults($deal));
    }

    public function send(SendDealProposalRequest $request, Deal $deal, DealProposal $proposal, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeProposalForDeal($deal, $proposal, 'send');

        $data = $request->validated();

        Mail::to($data['recipient_email'])->send(new DealProposalMail(
            $proposal,
            $data['email_subject'],
            $data['email_body'],
        ));

        $proposal->update([
            'status' => DealProposal::STATUS_SENT,
            'sent_at' => now(),
            'sent_by' => $request->user()->id,
            'recipient_email' => $data['recipient_email'],
            'email_subject' => $data['email_subject'],
            'email_body' => $data['email_body'],
        ]);

        $deal->forceFill([
            'last_activity_at' => now(),
        ])->save();

        $this->logProposalAction($logger, 'deal_proposal.sent', $deal, $proposal, 'Deal proposal sent to customer.', [
            'recipient_email' => $data['recipient_email'],
            'email_subject' => $data['email_subject'],
        ]);

        return back()->with('success', 'Proposta enviada ao cliente com sucesso.');
    }

    public function download(Deal $deal, DealProposal $proposal): StreamedResponse
    {
        $this->authorizeProposalForDeal($deal, $proposal, 'download');

        abort_unless(Storage::disk('local')->exists($proposal->path), 404);

        return Storage::disk('local')->download($proposal->path, $proposal->original_name);
    }

    public function destroy(Deal $deal, DealProposal $proposal, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeProposalForDeal($deal, $proposal, 'delete');

        $proposal->delete();
        $this->logProposalAction($logger, 'deal_proposal.deleted', $deal, $proposal, 'Deal proposal deleted.');

        return back()->with('success', 'Proposta removida com sucesso.');
    }

    private function authorizeProposalForDeal(Deal $deal, DealProposal $proposal, string $ability): void
    {
        abort_unless((int) $proposal->deal_id === (int) $deal->id, 404);
        abort_unless((int) $proposal->tenant_id === (int) $deal->tenant_id, 404);

        Gate::authorize($ability, $proposal);
    }

    /**
     * @return array{recipient_email: ?string, email_subject: string, email_body: string}
     */
    private function emailDefaults(Deal $deal): array
    {
        $deal->loadMissing(['person:id,name,email', 'entity:id,name,email']);
        $recipient = $deal->person?->email ?: $deal->entity?->email;
        $name = $deal->person?->name ?: $deal->entity?->name ?: 'Cliente';
        $userName = request()->user()?->name ?? config('app.name', 'FlowCRM');

        return [
            'recipient_email' => $recipient,
            'email_subject' => 'Proposta comercial - '.$deal->title,
            'email_body' => "Olá {$name},\n\nEspero que se encontre bem.\n\nEnvio em anexo a proposta comercial relativa a {$deal->title}.\nQualquer dúvida, fico totalmente disponível para ajudar.\n\nObrigado,\n{$userName}",
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logProposalAction(
        ActivityLogger $logger,
        string $action,
        Deal $deal,
        DealProposal $proposal,
        string $description,
        array $properties = [],
    ): void {
        $logger->log($action, 'deal_proposals', $proposal->tenant_id, $proposal, $description, [
            'deal_id' => $deal->id,
            ...$properties,
        ]);

        $logger->log($action, 'deals', $deal->tenant_id, $deal, $description, [
            'proposal_id' => $proposal->id,
            'proposal_name' => $proposal->original_name,
            ...$properties,
        ]);
    }
}
