<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft', self::Issued => 'Issued', self::PartiallyPaid => 'Part paid',
            self::Paid => 'Paid', self::Overdue => 'Overdue', self::Void => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray', self::Issued => 'info', self::PartiallyPaid => 'warning',
            self::Paid => 'success', self::Overdue => 'danger', self::Void => 'gray',
        };
    }

    /** Statuses that count as money owed. */
    public function isReceivable(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid, self::Overdue], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $o = [];
        foreach (self::cases() as $c) {
            $o[$c->value] = $c->label();
        }

        return $o;
    }
}
