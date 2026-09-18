<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Requests\Driver\UpdateSettingsRequest;
use App\Support\DriverPreferences;
use Illuminate\Http\JsonResponse;

class SettingsController extends BaseDriverController
{
    public function show(): JsonResponse
    {
        $driver = $this->driver()->load('metas');

        return $this->ok([
            'preferences' => DriverPreferences::forUser($driver),
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $driver = $this->driver()->load('metas');

        $preferences = DriverPreferences::update($driver, $request->validated());

        return $this->ok([
            'preferences' => $preferences,
        ], 'Settings saved.');
    }

    /**
     * The two support entries on the settings screen. The dispatch number comes
     * from the driver's own company so each driver calls their own dispatcher,
     * falling back to the company's account phone number.
     */
    public function support(): JsonResponse
    {
        $driver  = $this->driver();
        $company = $driver->dispatcher;

        $dispatchPhone = null;

        if ($company) {
            $company->load('metas');
            $dispatchPhone = $company->getMeta('dispatch_phone') ?: $company->phone_number;
        }

        return $this->ok([
            'dispatch_phone'     => $dispatchPhone,
            'company_name'       => $company?->name,
            'privacy_policy_url' => url('/privacy-policy'),
            'app_version_notes'  => null,
        ]);
    }
}
