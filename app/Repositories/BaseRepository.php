<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\QueryBuilder;

abstract class BaseRepository implements RepositoryInterface
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Explicit allow-list per Rule ("filtering, sorting via spatie/laravel-query-builder with explicit allow-lists"). */
    protected function allowedFilters(): array
    {
        return [];
    }

    protected function allowedSorts(): array
    {
        return ['created_at'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    public function query(): Builder
    {
        return ($this->modelClass())::query();
    }

    protected function filteredQuery(): QueryBuilder
    {
        return QueryBuilder::for($this->modelClass())
            ->allowedFilters(...$this->allowedFilters())
            ->allowedSorts(...$this->allowedSorts())
            ->defaultSort($this->defaultSort());
    }

    public function all(): Collection
    {
        return $this->filteredQuery()->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->filteredQuery()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findByUlid(string $ulid): ?Model
    {
        return $this->query()->where('ulid', $ulid)->first();
    }

    public function findOrFailByUlid(string $ulid): Model
    {
        return $this->query()->where('ulid', $ulid)->firstOrFail();
    }

    public function create(array $attributes): Model
    {
        return ($this->modelClass())::create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
