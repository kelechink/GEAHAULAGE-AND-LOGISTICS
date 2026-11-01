<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Driver;

use App\Domain\Crm\PhoneNumber;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Settings\CompanySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        $phone = PhoneNumber::normalise($data['phone']) ?? $data['phone'];
        $driver = Driver::query()->where('phone', $phone)->first();

        if ($driver === null || blank($driver->pin_hash) || ! Hash::check($data['pin'], (string) $driver->pin_hash)) {
            return response()->json(['message' => 'Wrong phone number or PIN.'], 422);
        }
        if (! $driver->is_active) {
            return response()->json(['message' => 'Your app access is switched off. Call Operations.'], 403);
        }

        $token = $driver->createToken($data['device_name'] ?? 'android', ['driver'])->plainTextToken;
        $driver->forceFill(['app_last_seen_at' => now()])->save();

        return response()->json(['token' => $token, 'driver' => self::profile($driver)]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();

        return response()->json(['driver' => self::profile($driver)]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user();
        $driver->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    public static function profile(Driver $driver): array
    {
        $driver->loadMissing('truck');

        return [
            'id' => $driver->id,
            'name' => $driver->name,
            'phone' => $driver->phone,
            'truck' => $driver->truck?->registration,
            // Tells the app how often to send its location (PRD 13: 60 s moving, 5 min parked).
            'location_interval_moving_s' => 60,
            'location_interval_parked_s' => 300,
            'operations_phone' => app(CompanySettings::class)->phone,
        ];
    }
}
