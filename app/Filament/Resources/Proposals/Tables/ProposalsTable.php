<?php

namespace App\Filament\Resources\Proposals\Tables;

use App\Services\ProposalService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProposalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan.name')
                    ->label('Plano')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'analysis' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('signature_status')
                    ->label('Assinatura')
                    ->formatStateUsing(fn ($record): string => $record->signatureStatusLabel())
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'new' => 'Nova',
                        'analysis' => 'Em análise',
                        'approved' => 'Aprovada',
                        'rejected' => 'Reprovada',
                    ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Aprovar e converter')
                    ->visible(fn ($record) => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        app(ProposalService::class)->approveAndConvert($record, auth()->user());

                        Notification::make()
                            ->title('Proposta convertida em associado')
                            ->success()
                            ->send();
                    }),
                Action::make('sign')
                    ->label('Marcar assinada')
                    ->visible(fn ($record) => $record->status === 'approved' && $record->signature_status !== 'signed')
                    ->action(function ($record): void {
                        $record->update([
                            'signature_status' => 'signed',
                            'signed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Assinatura registrada')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
