<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Generic data-access contract every concrete repository implements.
 * Controllers and services depend on this (or a model-specific extension
 * of it), never on Eloquent directly — see docs/design/01-domain-design.md
 * Rule 10 ("every state transition goes through a service class").
 */
interface RepositoryInterface
{
    public function query(): Builder;

    public function all(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Model;

    public function findByUlid(string $ulid): ?Model;

    public function findOrFailByUlid(string $ulid): Model;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}
