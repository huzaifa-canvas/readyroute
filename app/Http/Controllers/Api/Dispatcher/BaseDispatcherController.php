<?php

namespace App\Http\Controllers\Api\Dispatcher;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared base for every dispatcher endpoint.
 *
 * It exists so no controller has to remember to scope its own queries. A
 * dispatcher account and the panel staff under it are one company, and
 * companyId() is what resolves either to the same tenant — every query below
 * starts from that, so a mistyped id reaches a 404 rather than another
 * company's records.
 *
 * The web panel enforces the same rules in its own controllers. Nothing here
 * calls into those: the API has to be able to change shape without a view
 * changing underneath it, and a web controller that returned a redirect would
 * be useless to the app anyway.
 */
abstract class BaseDispatcherController extends Controller
{
    use ApiResponse;

    protected function user(): User
    {
        return auth()->user();
    }

    /**
     * The company every query in this API is scoped to.
     */
    protected function companyId(): int
    {
        return (int) $this->user()->companyId();
    }

    protected function trips(): Builder
    {
        return Trip::query()->where('dispatcher_id', $this->companyId());
    }

    protected function drivers(): Builder
    {
        return User::driversOf($this->companyId());
    }

    protected function vehicles(): Builder
    {
        return Vehicle::query()->where('dispatcher_id', $this->companyId());
    }

    protected function clients(): Builder
    {
        return Client::query()->where('dispatcher_id', $this->companyId());
    }

    /**
     * A page size the app asked for, within limits it cannot argue with.
     */
    protected function perPage(int $default = 15, int $max = 100): int
    {
        return max(1, min($max, (int) request()->input('per_page', $default)));
    }

    /**
     * Only the company account itself may change money or staff.
     *
     * Mirrors the web panel, where the same actions are closed to added panel
     * users unless they hold the permission.
     */
    protected function requireOwner(): void
    {
        abort_unless(
            $this->user()->isCompanyOwner() || $this->user()->isAdmin(),
            403,
            'Only the company account can do that.'
        );
    }
}
