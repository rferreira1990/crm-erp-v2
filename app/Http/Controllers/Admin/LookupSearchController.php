<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupSearchController extends Controller
{
    public function articles(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Article::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $limit = $this->resolveLimit($request->query('limit'));

        $items = Article::query()
            ->forCompany($companyId)
            ->when($request->boolean('active_only', true), fn ($query) => $query->where('is_active', true))
            ->when($request->boolean('moves_stock_only', false), fn ($query) => $query->where('moves_stock', true))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('designation', 'like', '%'.$search.'%')
                        ->orWhere('ean', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('designation')
            ->limit($limit)
            ->get(['id', 'code', 'designation', 'stock_quantity']);

        return response()->json([
            'data' => $items->map(fn (Article $article): array => [
                'id' => (int) $article->id,
                'text' => trim((string) $article->code) !== ''
                    ? $article->code.' - '.$article->designation
                    : (string) $article->designation,
                'code' => (string) ($article->code ?? ''),
                'name' => (string) $article->designation,
                'stock_quantity' => (float) $article->stock_quantity,
            ]),
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $limit = $this->resolveLimit($request->query('limit'));

        $items = Customer::query()
            ->forCompany($companyId)
            ->when($request->boolean('active_only', true), fn ($query) => $query->where('is_active', true))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('nif', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'nif', 'email']);

        return response()->json([
            'data' => $items->map(fn (Customer $customer): array => [
                'id' => (int) $customer->id,
                'text' => (string) $customer->name,
                'name' => (string) $customer->name,
                'nif' => (string) ($customer->nif ?? ''),
                'email' => (string) ($customer->email ?? ''),
            ]),
        ]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $limit = $this->resolveLimit($request->query('limit'));

        $items = Supplier::query()
            ->forCompany($companyId)
            ->when($request->boolean('active_only', true), fn ($query) => $query->where('is_active', true))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('nif', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'nif', 'email']);

        return response()->json([
            'data' => $items->map(fn (Supplier $supplier): array => [
                'id' => (int) $supplier->id,
                'text' => (string) $supplier->name,
                'name' => (string) $supplier->name,
                'nif' => (string) ($supplier->nif ?? ''),
                'email' => (string) ($supplier->email ?? ''),
            ]),
        ]);
    }

    private function resolveLimit(mixed $limit): int
    {
        $resolved = (int) $limit;

        return max(5, min(20, $resolved > 0 ? $resolved : 10));
    }
}
