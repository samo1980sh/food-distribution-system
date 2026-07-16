<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Support\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

abstract class AbstractReadOnlyController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** @return class-string<JsonResource> */
    abstract protected function resourceClass(): string;

    /** @return list<string> */
    protected function indexRelations(): array
    {
        return [];
    }

    /** @return list<string> */
    protected function detailRelations(): array
    {
        return $this->indexRelations();
    }

    /** @return list<string> */
    protected function searchColumns(): array
    {
        return [];
    }

    /** @return array<string, string> */
    protected function exactFilters(): array
    {
        return ['status' => 'status'];
    }

    protected function dateColumn(): ?string
    {
        return null;
    }

    /** @return list<string> */
    protected function sortColumns(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    protected function defaultSort(): string
    {
        return $this->dateColumn() ?? 'id';
    }

    protected function defaultDirection(): string
    {
        return 'desc';
    }

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $modelClass = $this->modelClass();
        Gate::authorize('viewAny', $modelClass);

        $query = $modelClass::query()->with($this->indexRelations());
        $this->applyFilters($query, $request);

        $paginator = $query
            ->paginate($request->perPage())
            ->withQueryString();

        $resourceClass = $this->resourceClass();
        $items = $resourceClass::collection(
            $paginator->getCollection(),
        )->resolve($request);

        return ApiResponse::paginated(
            $items,
            $paginator,
            $this->indexMessage(),
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $modelClass = $this->modelClass();
        $record = $modelClass::query()
            ->with($this->detailRelations())
            ->findOrFail($id);

        Gate::authorize('view', $record);

        $resourceClass = $this->resourceClass();

        return ApiResponse::success([
            'item' => $resourceClass::make($record)->resolve($request),
        ], $this->showMessage());
    }

    protected function applyAdditionalFilters(
        Builder $query,
        OperationalIndexRequest $request,
    ): void {
    }

    protected function indexMessage(): string
    {
        return 'تم تحميل البيانات.';
    }

    protected function showMessage(): string
    {
        return 'تم تحميل السجل.';
    }

    protected function applyFilters(
        Builder $query,
        OperationalIndexRequest $request,
    ): void {
        $search = trim((string) $request->validated('search', ''));

        if ($search !== '' && $this->searchColumns() !== []) {
            $query->where(function (Builder $query) use ($search): void {
                foreach ($this->searchColumns() as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', '%'.$search.'%');
                }
            });
        }

        foreach ($this->exactFilters() as $input => $column) {
            $value = $request->validated($input);

            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        if ($this->dateColumn() !== null) {
            if ($request->filled('date_from')) {
                $query->whereDate(
                    $this->dateColumn(),
                    '>=',
                    $request->validated('date_from'),
                );
            }

            if ($request->filled('date_to')) {
                $query->whereDate(
                    $this->dateColumn(),
                    '<=',
                    $request->validated('date_to'),
                );
            }
        }

        $this->applyAdditionalFilters($query, $request);

        if ($request->filled('updated_since')) {
            $query->where(
                'updated_at',
                '>',
                $request->validated('updated_since'),
            );
        }

        $requestedSort = (string) $request->validated('sort', '');
        $sort = in_array($requestedSort, $this->sortColumns(), true)
            ? $requestedSort
            : $this->defaultSort();
        $direction = (string) $request->validated(
            'direction',
            $this->defaultDirection(),
        );

        $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }
}
