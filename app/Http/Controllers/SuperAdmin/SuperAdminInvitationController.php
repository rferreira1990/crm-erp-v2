<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreSuperAdminInvitationRequest;
use App\Mail\SuperAdmin\CompanyAdminInvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SuperAdminInvitationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Invitation::class);

        $invitations = Invitation::query()
            ->with('company:id,name')
            ->latest()
            ->paginate(20);

        return view('superadmin.invitations.index', [
            'invitations' => $invitations,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Invitation::class);

        $companies = Company::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('superadmin.invitations.create', [
            'companies' => $companies,
        ]);
    }

    public function store(StoreSuperAdminInvitationRequest $request): RedirectResponse
    {
        $this->authorize('create', Invitation::class);

        $data = $request->validated();
        $normalizedEmail = Invitation::normalizeEmail($data['email']);
        $plainToken = Str::random(64);
        $tokenHash = Invitation::hashToken($plainToken);

        while (Invitation::query()->where('token', $tokenHash)->exists()) {
            $plainToken = Str::random(64);
            $tokenHash = Invitation::hashToken($plainToken);
        }

        $invitation = DB::transaction(function () use ($data, $request, $normalizedEmail, $tokenHash): Invitation {
            $companyId = (int) $data['company_id'];
            $role = (string) $data['role'];

            $duplicatePending = Invitation::query()
                ->pending()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->where('role', $role)
                ->lockForUpdate()
                ->exists();

            if ($duplicatePending) {
                throw ValidationException::withMessages([
                    'email' => 'Ja existe um convite pendente para este email nesta empresa.',
                ]);
            }

            $existingUser = User::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->lockForUpdate()
                ->exists();

            if ($existingUser) {
                throw ValidationException::withMessages([
                    'email' => 'Este email ja pertence a um utilizador desta empresa.',
                ]);
            }

            return Invitation::query()->create([
                'company_id' => $companyId,
                'invited_by' => $request->user()?->id,
                'email' => $normalizedEmail,
                'role' => $role,
                'token' => $tokenHash,
                'expires_at' => $data['expires_at'] ?? now()->addDays(7),
            ]);
        });

        Log::info('Superadmin invitation created', [
            'invitation_id' => $invitation->id,
            'company_id' => $invitation->company_id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'invited_by' => $invitation->invited_by,
            'expires_at' => optional($invitation->expires_at)->toDateTimeString(),
        ]);

        $emailSent = $this->sendInvitationEmail($invitation, $plainToken);

        return redirect()
            ->route('superadmin.invitations.index')
            ->with(
                'status',
                $emailSent
                    ? 'Convite criado e email enviado com sucesso.'
                    : 'Convite criado, mas o envio do email falhou. Verifique a configuracao SMTP.'
            );
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        $cancelled = $invitation->markAsCancelled();

        if ($cancelled) {
            Log::info('Superadmin invitation cancelled', [
                'invitation_id' => $invitation->id,
                'company_id' => $invitation->company_id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'cancelled_by' => auth()->id(),
            ]);
        }

        return redirect()
            ->route('superadmin.invitations.index')
            ->with('status', 'Convite cancelado com sucesso.');
    }

    private function sendInvitationEmail(Invitation $invitation, string $plainToken): bool
    {
        try {
            Mail::to($invitation->email)->send(new CompanyAdminInvitationMail($invitation, $plainToken));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Superadmin invitation email sending failed', [
                'context' => 'invitation_create',
                'invitation_id' => $invitation->id,
                'company_id' => $invitation->company_id,
                'email' => $invitation->email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
