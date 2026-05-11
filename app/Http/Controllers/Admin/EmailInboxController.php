<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Admin\SyncEmailInboxJob;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailInboxController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EmailMessage::class);

        $companyId = (int) $request->user()->company_id;
        $filters = $this->resolveFilters($request);
        $account = $this->resolveCompanyAccount($companyId);

        $messages = $this->buildInboxQuery($companyId, $account?->id, $filters)
            ->paginate(20)
            ->withQueryString();

        return view('admin.email.inbox.index', [
            'account' => $account,
            'messages' => $messages,
            'filters' => $filters,
        ]);
    }

    public function table(Request $request): View
    {
        $this->authorize('viewAny', EmailMessage::class);

        $companyId = (int) $request->user()->company_id;
        $filters = $this->resolveFilters($request);
        $account = $this->resolveCompanyAccount($companyId);

        $messages = $this->buildInboxQuery($companyId, $account?->id, $filters)
            ->paginate(20)
            ->withQueryString();

        return view('admin.email.inbox.partials.message-list', [
            'messages' => $messages,
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorize('sync', EmailMessage::class);

        $companyId = (int) $request->user()->company_id;
        $account = $this->resolveCompanyAccount($companyId);

        if (! $account) {
            return redirect()
                ->route('admin.email-inbox.index')
                ->withErrors(['email_sync' => 'Configure uma conta IMAP antes de sincronizar.']);
        }

        if (! $account->is_active) {
            return redirect()
                ->route('admin.email-inbox.index')
                ->withErrors(['email_sync' => 'A conta IMAP esta inativa. Ative-a antes de sincronizar.']);
        }

        SyncEmailInboxJob::dispatch(
            companyId: $companyId,
            emailAccountId: (int) $account->id,
            limit: 30
        )->onQueue('default');

        return redirect()
            ->route('admin.email-inbox.index')
            ->with('status', 'Sincronizacao IMAP agendada. Atualize a pagina em alguns segundos.');
    }

    /**
     * @param array{q:string,unread:bool,has_attachments:bool} $filters
     */
    private function buildInboxQuery(int $companyId, ?int $accountId, array $filters): Builder
    {
        $search = $filters['q'];

        return EmailMessage::query()
            ->forCompany($companyId)
            ->when($accountId !== null, function (Builder $query) use ($accountId): void {
                $query->where('email_account_id', $accountId);
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('from_email', 'like', '%'.$search.'%')
                        ->orWhere('from_name', 'like', '%'.$search.'%')
                        ->orWhere('subject', 'like', '%'.$search.'%')
                        ->orWhere('snippet', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['unread'], function (Builder $query): void {
                $query->where('is_seen', false);
            })
            ->when($filters['has_attachments'], function (Builder $query): void {
                $query->where('has_attachments', true);
            })
            ->orderByDesc('received_at')
            ->orderByDesc('id');
    }

    /**
     * @return array{q:string,unread:bool,has_attachments:bool}
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'unread' => $request->boolean('unread'),
            'has_attachments' => $request->boolean('has_attachments'),
        ];
    }

    private function resolveCompanyAccount(int $companyId): ?EmailAccount
    {
        return EmailAccount::query()
            ->forCompany($companyId)
            ->first();
    }

}
