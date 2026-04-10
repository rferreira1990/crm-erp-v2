<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyUserInvitationRequest;
use App\Mail\SuperAdmin\CompanyAdminInvitationMail;
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

class CompanyUserInvitationController extends Controller
{
    private const INTERNAL_ROLES = ['company_admin', 'company_user'];

    public function create(): View
    {
        $this->authorize('create', Invitation::class);

        return view('admin.users.invitations.create', [
            'assignableRoles' => self::INTERNAL_ROLES,
        ]);
    }

    public function store(StoreCompanyUserInvitationRequest $request): RedirectResponse
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

        $invitation = DB::transaction(function () use ($request, $data, $normalizedEmail, $tokenHash): Invitation {
            $companyId = (int) $request->user()->company_id;
            $role = (string) $data['role'];

            if (! in_array($role, self::INTERNAL_ROLES, true)) {
                throw ValidationException::withMessages([
                    'role' => 'Role invalida para este contexto.',
                ]);
            }

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
                    'email' => 'Este email ja pertence a um utilizador da sua empresa.',
                ]);
            }

            return Invitation::query()->create([
                'company_id' => $companyId,
                'invited_by' => $request->user()->id,
                'email' => $normalizedEmail,
                'role' => $role,
                'token' => $tokenHash,
                'expires_at' => $data['expires_at'] ?? now()->addDays(7),
            ]);
        });

        Log::info('Company admin invitation created', [
            'context' => 'company_users',
            'invitation_id' => $invitation->id,
            'company_id' => $invitation->company_id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'invited_by' => $invitation->invited_by,
        ]);

        $emailSent = $this->sendInvitationEmail($invitation, $plainToken);

        return redirect()
            ->route('admin.users.index')
            ->with(
                'status',
                $emailSent
                    ? 'Convite enviado com sucesso.'
                    : 'Convite criado, mas o envio do email falhou.'
            );
    }

    public function destroy(int $invitation): RedirectResponse
    {
        $companyId = (int) auth()->user()->company_id;
        $companyInvitation = Invitation::query()
            ->where('company_id', $companyId)
            ->whereKey($invitation)
            ->firstOrFail();
        $this->authorize('delete', $companyInvitation);

        $cancelled = $companyInvitation->markAsCancelled();

        if ($cancelled) {
            Log::info('Company admin invitation cancelled', [
                'context' => 'company_users',
                'invitation_id' => $companyInvitation->id,
                'company_id' => $companyInvitation->company_id,
                'email' => $companyInvitation->email,
                'cancelled_by' => auth()->id(),
            ]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Convite cancelado com sucesso.');
    }

    private function sendInvitationEmail(Invitation $invitation, string $plainToken): bool
    {
        try {
            Mail::to($invitation->email)->send(new CompanyAdminInvitationMail($invitation, $plainToken));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Company invitation email sending failed', [
                'context' => 'company_users',
                'invitation_id' => $invitation->id,
                'company_id' => $invitation->company_id,
                'email' => $invitation->email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
