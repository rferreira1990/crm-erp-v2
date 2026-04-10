<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCompanyUserRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CompanyUserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $user = auth()->user();
        $companyId = (int) $user->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $role = (string) $request->query('role', '');

        $usersQuery = User::query()
            ->where('company_id', $companyId)
            ->where('is_super_admin', false)
            ->with('roles:id,name');

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $usersQuery->where('is_active', $status === 'active');
        }

        if (in_array($role, User::companyRoleNames(), true)) {
            $usersQuery->role($role, 'web');
        }

        $users = $usersQuery
            ->orderBy('name')
            ->paginate(20, ['*'], 'users_page')
            ->withQueryString();

        $invitations = Invitation::query()
            ->where('company_id', $companyId)
            ->latest()
            ->paginate(10, ['*'], 'invitations_page')
            ->withQueryString();

        $assignableRoles = Role::query()
            ->whereIn('name', User::companyRoleNames())
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.users.index', [
            'users' => $users,
            'invitations' => $invitations,
            'assignableRoles' => $assignableRoles,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'role' => $role,
            ],
        ]);
    }

    public function update(UpdateCompanyUserRequest $request, int $companyUser): RedirectResponse
    {
        $authUser = $request->user();
        $companyId = (int) $authUser->company_id;
        $roleName = (string) $request->validated('role');

        if (! in_array($roleName, User::companyRoleNames(), true)) {
            return back()->withErrors([
                'role' => 'Role invalida para este contexto.',
            ]);
        }

        if (! Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
            return back()->withErrors([
                'role' => 'Role invalida para a empresa.',
            ]);
        }

        try {
            $companyUserModel = DB::transaction(function () use ($authUser, $companyId, $companyUser, $roleName): User {
                $companyUserModel = $this->findCompanyUserForUpdateOrFail($companyId, $companyUser);
                $this->authorize('update', $companyUserModel);

                if ((int) $authUser->id === (int) $companyUserModel->id) {
                    throw ValidationException::withMessages([
                        'role' => 'Nao pode alterar a sua propria role.',
                    ]);
                }

                if (
                    $companyUserModel->is_active
                    && $companyUserModel->hasRole(User::ROLE_COMPANY_ADMIN)
                    && $roleName !== User::ROLE_COMPANY_ADMIN
                    && $this->activeCompanyAdminCountForUpdate($companyId) <= 1
                ) {
                    throw ValidationException::withMessages([
                        'role' => 'Nao e possivel remover a role de administrador ao ultimo admin ativo da empresa.',
                    ]);
                }

                $companyUserModel->syncRoles([$roleName]);

                return $companyUserModel;
            }, 3);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        Log::info('Company user role updated', [
            'context' => 'company_users',
            'company_id' => $companyId,
            'target_user_id' => $companyUserModel->id,
            'updated_by' => $authUser->id,
            'role' => $roleName,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Role do utilizador atualizada com sucesso.');
    }

    public function toggleActive(Request $request, int $companyUser): RedirectResponse
    {
        $authUser = $request->user();
        $companyId = (int) $authUser->company_id;

        try {
            $companyUserModel = DB::transaction(function () use ($authUser, $companyId, $companyUser): User {
                $companyUserModel = $this->findCompanyUserForUpdateOrFail($companyId, $companyUser);
                $this->authorize('update', $companyUserModel);

                if ((int) $authUser->id === (int) $companyUserModel->id) {
                    throw ValidationException::withMessages([
                        'user' => 'Nao pode desativar a sua propria conta.',
                    ]);
                }

                if (
                    $companyUserModel->is_active
                    && $companyUserModel->hasRole(User::ROLE_COMPANY_ADMIN)
                    && $this->activeCompanyAdminCountForUpdate($companyId) <= 1
                ) {
                    throw ValidationException::withMessages([
                        'user' => 'Nao e possivel desativar o ultimo administrador ativo da empresa.',
                    ]);
                }

                $companyUserModel->forceFill([
                    'is_active' => ! $companyUserModel->is_active,
                ])->save();

                return $companyUserModel;
            }, 3);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        Log::info('Company user active state toggled', [
            'context' => 'company_users',
            'company_id' => $companyId,
            'target_user_id' => $companyUserModel->id,
            'changed_by' => $authUser->id,
            'is_active' => $companyUserModel->is_active,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with(
                'status',
                $companyUserModel->is_active
                    ? 'Utilizador ativado com sucesso.'
                    : 'Utilizador desativado com sucesso.'
            );
    }

    private function findCompanyUserForUpdateOrFail(int $companyId, int $userId): User
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_super_admin', false)
            ->whereKey($userId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function activeCompanyAdminCountForUpdate(int $companyId): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', User::ROLE_COMPANY_ADMIN)
                    ->where('guard_name', 'web');
            })
            ->lockForUpdate()
            ->get(['id'])
            ->count();
    }
}
