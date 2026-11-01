<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Model;

/**
 * One permission controls a whole resource. Set $viewPermission and, for editable resources, $managePermission.
 */
trait GuardsByPermission
{
    abstract protected static function viewPermission(): Permission;

    protected static function managePermission(): ?Permission
    {
        return null;
    }

    private static function allows(?Permission $permission): bool
    {
        return $permission !== null && (auth()->user()?->can($permission->value) ?? false);
    }

    public static function canViewAny(): bool
    {
        return self::allows(static::viewPermission()) || self::allows(static::managePermission());
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::allows(static::managePermission());
    }

    public static function canEdit(Model $record): bool
    {
        return self::allows(static::managePermission());
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
