<?php

namespace App\Jobs\Admin;

use App\Models\EmailAccount;
use App\Services\Admin\EmailInboxSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class SyncEmailInboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $companyId,
        public int $emailAccountId,
        public int $limit = 30
    ) {
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('email-sync:'.$this->companyId.':'.$this->emailAccountId))
                ->expireAfter(120),
        ];
    }

    public function handle(EmailInboxSyncService $syncService): void
    {
        $account = EmailAccount::query()
            ->forCompany($this->companyId)
            ->whereKey($this->emailAccountId)
            ->first();

        if (! $account || ! $account->is_active) {
            return;
        }

        $syncService->syncLatestInbox($account, max(1, min(100, $this->limit)));
    }
}

