<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCompanyUserRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class CompanyUserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $user = auth()->user();
        $companyId = (int) $user->company_id;

        $users = User::query()
            ->where('company_id', $companyId)
            ->where('is_super_admin', false)
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate(20, ['*'], 'users_page');

        $invitations = Invitation::query()
            ->where('company_id', $companyId)
            ->latest()
            ->paginate(10, ['*'], 'invitations_page');

        $assignableRoles = Role::query()
            ->whereIn('name', ['company_admin', 'company_user'])
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.users.index', [
            'users' => $users,
            'invitations' => $invitations,
            'assignableRoles' => $assignableRoles,
        ]);
    }

    public function update(UpdateCompanyUserRequest $request, User $companyUser): RedirectResponse
    {
        $this->authorize('update', $companyUser);

        if ((int) $request->user()->id === (int) $companyUser->id) {
            return back()->withErrors([
                'role' => 'Nao pode alterar a sua propria role.',
            ]);
        }

        $roleName = (string) $request->validated('role');

        if (! Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
            return back()->withErrors([
                'role' => 'Role invalida para a empresa.',
            ]);
        }

        $companyUser->syncRoles([$roleName]);

        Log::info('Company user role updated', [
            'context' => 'company_users',
            'company_id' => $request->user()->company_id,
            'target_user_id' => $companyUser->id,
            'updated_by' => $request->user()->id,
            'role' => $roleName,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Role do utilizador atualizada com sucesso.');
    }

    public function toggleActive(User $companyUser): RedirectResponse
    {
        $this->authorize('update', $companyUser);

        $authUser = auth()->user();

        if ((int) $authUser->id === (int) $companyUser->id) {
            return back()->withErrors([
                'user' => 'Nao pode desativar a sua propria conta.',
            ]);
        }

        $companyUser->forceFill([
            'is_active' => ! $companyUser->is_active,
        ])->save();

        Log::info('Company user active state toggled', [
            'context' => 'company_users',
            'company_id' => $authUser->company_id,
            'target_user_id' => $companyUser->id,
            'changed_by' => $authUser->id,
            'is_active' => $companyUser->is_active,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Estado do utilizador atualizado com sucesso.');
    }
}
