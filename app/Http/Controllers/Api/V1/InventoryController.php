<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockLevelResource;
use App\Http\Resources\StockMovementResource;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function levels(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockLevel::class);

        return StockLevelResource::collection($this->inventory->levels());
    }

    public function movements(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockMovement::class);

        return StockMovementResource::collection($this->inventory->movements());
    }
}
