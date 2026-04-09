<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreCompanyRequest;
use App\Http\Requests\SuperAdmin\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CompanyController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Company::class);

        $companies = Company::query()
            ->orderBy('name')
            ->paginate(15);

        return view('superadmin.companies.index', [
            'companies' => $companies,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Company::class);

        return view('superadmin.companies.create');
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $this->authorize('create', Company::class);

        $payload = $request->validated();
        $payload['is_active'] = $request->boolean('is_active', true);

        Company::query()->create($payload);

        return redirect()
            ->route('superadmin.companies.index')
            ->with('status', 'Empresa criada com sucesso.');
    }

    public function edit(Company $company): View
    {
        $this->authorize('update', $company);

        return view('superadmin.companies.edit', [
            'company' => $company,
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $payload = $request->validated();
        $payload['is_active'] = $request->boolean('is_active');

        $company->update($payload);

        return redirect()
            ->route('superadmin.companies.index')
            ->with('status', 'Empresa atualizada com sucesso.');
    }

    public function toggleActive(Company $company): RedirectResponse
    {
        $this->authorize('toggle', $company);

        $company->update([
            'is_active' => ! $company->is_active,
        ]);

        return redirect()
            ->route('superadmin.companies.index')
            ->with('status', 'Estado da empresa atualizado com sucesso.');
    }
}
