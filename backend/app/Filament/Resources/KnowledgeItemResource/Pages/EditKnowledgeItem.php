<?php

declare(strict_types=1);

namespace App\Filament\Resources\KnowledgeItemResource\Pages;

use App\Filament\Resources\KnowledgeItemResource;
use Filament\Resources\Pages\EditRecord;

class EditKnowledgeItem extends EditRecord
{
    protected static string $resource = KnowledgeItemResource::class;
}
