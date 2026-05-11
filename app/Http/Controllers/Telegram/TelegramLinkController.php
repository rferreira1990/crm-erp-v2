<?php

namespace App\Http\Controllers\Telegram;

use App\Exceptions\Telegram\TelegramLinkException;
use App\Http\Controllers\Controller;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Telegram\TelegramLinkCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TelegramLinkController extends Controller
{
    public function __construct(
        private readonly TelegramLinkCodeService $linkCodeService
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('company.telegram.link.manage'), 403);

        /** @var User $user */
        $user = $request->user();
        $companyId = (int) $user->company_id;

        abort_if($companyId <= 0, 404);

        $activeLink = TelegramUserLink::query()
            ->forCompany($companyId)
            ->where('user_id', (int) $user->id)
            ->active()
            ->latest('id')
            ->first();

        $activeCode = $this->linkCodeService->getActiveCodeForUser($user);

        return view('telegram.link', [
            'activeLink' => $activeLink,
            'activeCode' => $activeCode,
        ]);
    }

    public function generateCode(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('company.telegram.link.manage'), 403);

        /** @var User $user */
        $user = $request->user();

        try {
            $code = $this->linkCodeService->generateForUser($user);
        } catch (TelegramLinkException $exception) {
            return redirect()
                ->route('admin.telegram.link.index')
                ->withErrors([
                    'telegram_link' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('admin.telegram.link.index')
            ->with('status', 'Codigo de ligacao gerado com sucesso.')
            ->with('telegram_link_code', $code->code);
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('company.telegram.link.manage'), 403);

        /** @var User $user */
        $user = $request->user();
        $companyId = (int) $user->company_id;

        abort_if($companyId <= 0, 404);

        TelegramUserLink::query()
            ->forCompany($companyId)
            ->where('user_id', (int) $user->id)
            ->active()
            ->update([
                'is_active' => false,
            ]);

        $this->linkCodeService->deactivateActiveCodesForUser($user);

        return redirect()
            ->route('admin.telegram.link.index')
            ->with('status', 'Ligacao Telegram removida.');
    }
}
