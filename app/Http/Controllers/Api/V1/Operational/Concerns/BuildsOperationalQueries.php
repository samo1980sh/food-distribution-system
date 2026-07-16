<?php

namespace App\Http\Controllers\Api\V1\Operational\Concerns;

use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use Illuminate\Database\Eloquent\Builder;

trait BuildsOperationalQueries
{
    /** @param list<string> $columns */
    protected function applySearch(
        Builder $query,
        OperationalIndexRequest $request,
        array $columns,
    ): Builder {
        $search = $request->searchTerm();

        if ($search === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($columns, $search): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($column, 'like', '%'.$search.'%');
            }
        });
    }

    protected function applyDateRange(
        Builder $query,
        OperationalIndexRequest $request,
        string $column,
    ): Builder {
        return $query
            ->when($request->validated('date_from'), fn (Builder $q, $date): Builder => $q->whereDate($column, '>=', $date))
            ->when($request->validated('date_to'), fn (Builder $q, $date): Builder => $q->whereDate($column, '<=', $date));
    }

    /** @param list<string> $filters */
    protected function applyIdFilters(
        Builder $query,
        OperationalIndexRequest $request,
        array $filters,
    ): Builder {
        foreach ($filters as $filter) {
            $value = $request->validated($filter);

            if ($value !== null) {
                $query->where($filter, (int) $value);
            }
        }

        return $query;
    }
}
