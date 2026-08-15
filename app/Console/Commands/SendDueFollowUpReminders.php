<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Notifications\FollowUpDue;
use Illuminate\Console\Command;

class SendDueFollowUpReminders extends Command
{
    protected $signature = 'app:send-due-follow-up-reminders';

    protected $description = 'Send a push notification for every follow-up that has come due.';

    public function handle(): void
    {
        FollowUp::query()
            ->pending()
            ->whereNull('notified_at')
            ->where('due_at', '<=', now())
            ->with('user')
            ->each(function (FollowUp $followUp) {
                if ($followUp->user) {
                    $followUp->user->notify(new FollowUpDue($followUp));
                }

                $followUp->update(['notified_at' => now()]);
            });
    }
}
