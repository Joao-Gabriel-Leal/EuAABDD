<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Services\BillingService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label('Associado')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('number')
                    ->label('Número')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'monthly' => 'Mensalidade',
                        'reservation' => 'Reserva',
                        'invitation' => 'Convite',
                        'medical_exam' => 'Exame médico',
                        'extra' => 'Avulsa',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'awaiting_review' => 'warning',
                        'open', 'pending' => 'info',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method')
                    ->label('Meio')
                    ->searchable(),
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
                        'open' => 'Aberta',
                        'overdue' => 'Vencida',
                        'awaiting_review' => 'Comprovante em análise',
                        'paid' => 'Paga',
                        'cancelled' => 'Cancelada',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'monthly' => 'Mensalidade',
                        'reservation' => 'Reserva',
                        'invitation' => 'Convite',
                        'extra' => 'Avulsa',
                    ]),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('Baixar')
                    ->visible(fn ($record) => ! in_array($record->status, ['paid', 'cancelled'], true))
                    ->form([
                        TextInput::make('amount')
                            ->label('Valor recebido')
                            ->numeric()
                            ->default(fn ($record) => $record->amount)
                            ->required(),
                        Select::make('method')
                            ->label('Meio')
                            ->options([
                                'Boleto Banco do Brasil' => 'Boleto Banco do Brasil',
                                'Débito em conta Banco do Brasil' => 'Débito em conta Banco do Brasil',
                                'QR App AABB' => 'QR App AABB',
                                'Cartão presencial' => 'Cartão presencial',
                            ])
                            ->required(),
                        DatePicker::make('paid_at')
                            ->label('Data do pagamento')
                            ->default(now())
                            ->required(),
                        TextInput::make('manual_reference')
                            ->label('Referência'),
                        FileUpload::make('proof_path')
                            ->label('Comprovante')
                            ->directory('payment-proofs'),
                        Textarea::make('notes')
                            ->label('Observações'),
                    ])
                    ->action(function ($record, array $data): void {
                        app(BillingService::class)->recordManualPayment($record, $data, auth()->user());

                        Notification::make()
                            ->title('Baixa registrada')
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
