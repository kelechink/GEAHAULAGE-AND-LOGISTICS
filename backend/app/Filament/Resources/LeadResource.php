<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\Permission;
use App\Filament\Concerns\GuardsByPermission;
use App\Filament\Resources\LeadResource\Pages;
use App\Models\Crm\Lead;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only lead list so website enquiries are visible from Phase 1. Phase 2 adds the pipeline and editing.
 */
class LeadResource extends Resource
{
    use GuardsByPermission;

    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Leads';

    protected static function viewPermission(): Permission
    {
        return Permission::CrmView;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Received')->since()->sortable(),
                Tables\Columns\TextColumn::make('contact.name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('contact.phone')->label('Phone')->copyable(),
                Tables\Columns\TextColumn::make('service_interest')->label('Service')->limit(30),
                Tables\Columns\TextColumn::make('route')->label('Route')
                    ->state(fn (Lead $r): string => ($r->origin ?? '?').' → '.($r->destination ?? '?')),
                Tables\Columns\TextColumn::make('stage')->badge()->formatStateUsing(fn (LeadStage $state): string => $state->label()),
                Tables\Columns\TextColumn::make('next_follow_up_at')->label('Follow up by')->dateTime('D j M, g:ia', 'Africa/Lagos')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage')->options(collect(LeadStage::cases())->mapWithKeys(fn (LeadStage $s) => [$s->value => $s->label()])->all()),
                Tables\Filters\SelectFilter::make('source')->options(collect(LeadSource::cases())->mapWithKeys(fn (LeadSource $s) => [$s->value => $s->label()])->all()),
            ])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Contact')->columns(3)->schema([
                Infolists\Components\TextEntry::make('contact.name')->label('Name'),
                Infolists\Components\TextEntry::make('contact.phone')->label('Phone')->copyable(),
                Infolists\Components\TextEntry::make('contact.email')->label('Email')->placeholder('Not given'),
                Infolists\Components\TextEntry::make('company.name')->label('Company')->placeholder('Not given'),
            ]),
            Infolists\Components\Section::make('Request')->columns(3)->schema([
                Infolists\Components\TextEntry::make('service_interest')->label('Service')->placeholder('Not given'),
                Infolists\Components\TextEntry::make('origin')->label('From'),
                Infolists\Components\TextEntry::make('destination')->label('To'),
                Infolists\Components\TextEntry::make('cargo')->label('Cargo details')->columnSpanFull()->placeholder('Not given'),
                Infolists\Components\TextEntry::make('source')->formatStateUsing(fn (LeadSource $state): string => $state->label()),
                Infolists\Components\TextEntry::make('stage')->formatStateUsing(fn (LeadStage $state): string => $state->label()),
                Infolists\Components\TextEntry::make('next_follow_up_at')->label('Follow up by')->dateTime('D j M, g:ia', 'Africa/Lagos'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'view' => Pages\ViewLead::route('/{record}'),
        ];
    }
}
