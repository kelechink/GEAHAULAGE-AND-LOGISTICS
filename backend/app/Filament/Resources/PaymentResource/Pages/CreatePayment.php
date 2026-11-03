<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Domain\Finance\FinanceException;
use App\Domain\Finance\PaymentService;
use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentResource;
use App\Models\Customer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PaymentService::class)->record(
                Customer::query()->findOrFail($data['customer_id']),
                (int) $data['amount_kobo'],
                PaymentMethod::from($data['method']),
                Carbon::parse($data['received_on']),
                $data['reference'] ?? null,
                (int) ($data['wht_kobo'] ?? 0),
                null,
                auth()->user(),
                $data['notes'] ?? null,
            );
        } catch (FinanceException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw new Halt;
        }
    }
}
