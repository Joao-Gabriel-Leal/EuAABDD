<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Models\Member;
use App\Models\Proposal;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('documentable_type')
                    ->label('Vinculado a')
                    ->options([
                        Member::class => 'Associado',
                        Proposal::class => 'Proposta',
                    ]),
                TextInput::make('documentable_id')
                    ->label('ID do vínculo')
                    ->numeric(),
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'rg' => 'RG',
                        'cpf' => 'CPF',
                        'proof_of_address' => 'Comprovante de residência',
                        'photo' => 'Foto',
                        'contract' => 'Proposta/contrato',
                        'medical_exam' => 'Exame médico',
                        'other' => 'Outro',
                    ])
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                FileUpload::make('path')
                    ->label('Arquivo')
                    ->directory('member-documents')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png']),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pendente',
                        'approved' => 'Aprovado',
                        'rejected' => 'Rejeitado',
                    ])
                    ->required()
                    ->default('pending'),
            ]);
    }
}
