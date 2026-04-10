<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class InvitationAcceptanceController extends Controller
{
    public function create(Request $request): View
    {
        $plainToken = trim((string) $request->query('token', ''));
        $invitation = $this->resolvePendingInvitation($plainToken);

        if (! $invitation) {
            $this->logInvalidAttempt('invalid_or_unavailable', $request);

            return view('invitations.invalid', [
                'message' => 'O convite e invalido ou ja nao esta disponivel.',
            ]);
        }

        return view('invitations.accept', [
            'token' => $plainToken,
            'invitation' => $invitation,
        ]);
    }

    public function store(AcceptInvitationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $plainToken = (string) $data['token'];

        try {
            $user = DB::transaction(function () use ($data, $plainToken, $request): User {
                $invitation = Invitation::query()
                    ->with('company:id,name,is_active')
                    ->where('token', Invitation::hashToken($plainToken))
                    ->lockForUpdate()
                    ->first();

                if (! $this->isValidPendingInvitation($invitation, $plainToken)) {
                    $this->logInvalidAttempt('invalid_or_unavailable', $request, $invitation);

                    throw ValidationException::withMessages([
                        'token' => 'O convite e invalido ou ja nao esta disponivel.',
                    ]);
                }

                $normalizedEmail = Invitation::normalizeEmail((string) $invitation->email);

                $userAlreadyExists = User::query()
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->lockForUpdate()
                    ->exists();

                if ($userAlreadyExists) {
                    $this->logInvalidAttempt('email_already_registered', $request, $invitation);

                    throw ValidationException::withMessages([
                        'email' => 'Ja existe uma conta com este email. Inicie sessao para continuar.',
                    ]);
                }

                $roleName = (string) $invitation->role;
                $roleExists = Role::query()
                    ->where('name', $roleName)
                    ->where('guard_name', 'web')
                    ->exists();

                if (! $roleExists) {
                    $this->logInvalidAttempt('invalid_role', $request, $invitation);

                    throw ValidationException::withMessages([
                        'token' => 'O convite e invalido ou ja nao esta disponivel.',
                    ]);
                }

                $user = User::query()->create([
                    'name' => (string) $data['name'],
                    'email' => $normalizedEmail,
                    'password' => (string) $data['password'],
                    'company_id' => $invitation->company_id,
                    'invited_by' => $invitation->invited_by,
                    'is_super_admin' => false,
                    'is_active' => true,
                ]);

                $user->assignRole($roleName);

                if (! $invitation->markAsAccepted()) {
                    throw new \RuntimeException('Unable to mark invitation as accepted.');
                }

                Log::info('Invitation accepted successfully', [
                    'context' => 'invitation_accept',
                    'invitation_id' => $invitation->id,
                    'company_id' => $invitation->company_id,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $roleName,
                    'accepted_at' => optional($invitation->accepted_at)->toDateTimeString(),
                ]);

                return $user;
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Invitation acceptance failed unexpectedly', [
                'context' => 'invitation_accept',
                'error' => $exception->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            return redirect()
                ->route('invitations.accept.create', ['token' => $plainToken])
                ->withInput($request->safe()->except(['password', 'password_confirmation']))
                ->withErrors([
                    'token' => 'Nao foi possivel concluir a aceitacao do convite. Tente novamente.',
                ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Conta criada com sucesso. Bem-vindo.');
    }

    private function resolvePendingInvitation(string $plainToken): ?Invitation
    {
        if ($plainToken === '') {
            return null;
        }

        $invitation = Invitation::query()
            ->with('company:id,name,is_active')
            ->where('token', Invitation::hashToken($plainToken))
            ->first();

        return $this->isValidPendingInvitation($invitation, $plainToken)
            ? $invitation
            : null;
    }

    private function isValidPendingInvitation(?Invitation $invitation, string $plainToken): bool
    {
        if (! $invitation) {
            return false;
        }

        if (! $invitation->matchesToken($plainToken)) {
            return false;
        }

        if (! $invitation->isPending()) {
            return false;
        }

        if (! $invitation->company || ! $invitation->company->is_active) {
            return false;
        }

        return true;
    }

    private function logInvalidAttempt(string $reason, Request $request, ?Invitation $invitation = null): void
    {
        Log::warning('Invalid invitation acceptance attempt', [
            'context' => 'invitation_accept',
            'reason' => $reason,
            'invitation_id' => $invitation?->id,
            'company_id' => $invitation?->company_id,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
