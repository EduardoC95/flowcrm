<?php

namespace App\Console\Commands;

use App\Mail\DealFollowUpMail;
use App\Models\DealFollowUp;
use App\Models\DealFollowUpEmail;
use App\Models\DealStage;
use App\Services\ActivityLogger;
use App\Services\FollowUpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDueDealFollowUpsCommand extends Command
{
    protected $signature = 'followups:send-due';

    protected $description = 'Send due automated deal follow-up emails.';

    public function handle(FollowUpService $followUpService, ActivityLogger $logger): int
    {
        $now = now();

        if (! $followUpService->isWithinBusinessHours($now)) {
            $this->components->info('Outside business hours. No follow-ups sent.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        DealFollowUp::query()
            ->with(['deal.stage', 'deal.person', 'deal.entity', 'deal.owner'])
            ->where('status', DealFollowUp::STATUS_ACTIVE)
            ->whereNotNull('next_send_at')
            ->where('next_send_at', '<=', $now)
            ->orderBy('next_send_at')
            ->chunkById(50, function ($followUps) use (&$sent, &$skipped, $followUpService, $logger, $now) {
                foreach ($followUps as $followUp) {
                    $deal = $followUp->deal;

                    $stage = $deal?->stage()->first();

                    if (! $deal || $stage?->slug !== DealStage::SLUG_FOLLOW_UP) {
                        if ($deal) {
                            $followUpService->cancelForDeal($deal, 'Negócio saiu do estado Follow Up');
                        }

                        $skipped++;

                        continue;
                    }

                    $recipient = $followUpService->resolveRecipientEmail($deal);

                    if (! $recipient) {
                        $followUpService->scheduleNext($followUp);
                        $logger->log(
                            'follow_up.skipped_missing_recipient',
                            'follow_ups',
                            $deal->tenant_id,
                            $deal,
                            'Automated follow-up skipped because no recipient email was found.',
                            ['deal_follow_up_id' => $followUp->id],
                        );
                        $skipped++;

                        continue;
                    }

                    $template = $followUpService->pickTemplate($followUp);

                    if (! $template) {
                        $followUpService->scheduleNext($followUp);
                        $skipped++;

                        continue;
                    }

                    Mail::to($recipient)->send(new DealFollowUpMail($deal, $template, $deal->owner));

                    $rendered = $followUpService->renderTemplate($template, $deal, $deal->owner);

                    DealFollowUpEmail::create([
                        'tenant_id' => $deal->tenant_id,
                        'deal_id' => $deal->id,
                        'deal_follow_up_id' => $followUp->id,
                        'follow_up_template_id' => $template->id,
                        'sent_by' => null,
                        'recipient_email' => $recipient,
                        'subject' => $rendered['subject'],
                        'body' => $rendered['body'],
                        'sent_at' => $now,
                    ]);

                    $followUp->forceFill([
                        'last_sent_at' => $now,
                        'sent_count' => $followUp->sent_count + 1,
                    ])->save();

                    $followUpService->scheduleNext($followUp);

                    $deal->forceFill(['last_activity_at' => $now])->save();

                    $logger->log(
                        'follow_up.email_sent',
                        'follow_ups',
                        $deal->tenant_id,
                        $deal,
                        'Automated follow-up email sent.',
                        [
                            'deal_follow_up_id' => $followUp->id,
                            'follow_up_template_id' => $template->id,
                            'recipient_email' => $recipient,
                            'subject' => $rendered['subject'],
                        ],
                    );

                    $sent++;
                }
            });

        $this->components->info("Follow-ups sent: {$sent}. Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
