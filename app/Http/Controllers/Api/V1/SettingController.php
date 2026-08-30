<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Setting\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Services\SettingService;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET|PUT /api/v1/settings — branch-scoped key/value config with a
 * shop-wide fallback (docs/design/01-domain-design.md §2.1 / §6). The
 * branch is always the authenticated user's own; there is no cross-branch
 * settings access through the API.
 */
class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless((bool) $request->user()?->can('settings.manage'), 403, 'You do not have permission to manage settings.');

        return SettingResource::collection($this->settings->resolved($this->branchId($request)));
    }

    public function update(UpdateSettingsRequest $request): AnonymousResourceCollection
    {
        $resolved = $this->settings->apply(
            $this->branchId($request),
            $request->validated()['settings'],
        );

        return SettingResource::collection($resolved);
    }

    private function branchId(Request $request): int
    {
        $branchId = $request->user()?->branch_id;

        if ($branchId === null) {
            // Every real staff account has a branch; a tokenless/branchless
            // caller has nothing to scope settings to.
            throw new ApiException(ErrorCode::Forbidden, 'Your account is not attached to a branch.');
        }

        return $branchId;
    }
}
