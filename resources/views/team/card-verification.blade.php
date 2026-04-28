@extends('layouts.club', ['title' => 'Validação de Carteirinha | Portaria AABB'])

@section('content')
    <section class="verification-hero">
        <div>
            <p class="overline">Portaria AABB</p>
            <h1>Validação de carteirinha</h1>
            <p>Leitura interna por QR Code para liberar ou bloquear acesso com segurança.</p>
        </div>
        <a class="club-button club-button--yellow" href="{{ route('team.dashboard') }}">Voltar para equipe</a>
    </section>

    <section class="verification-wrap">
        @if(! $member)
            <article class="verification-card verification-card--blocked">
                <span>Carteirinha inválida</span>
                <h2>Acesso bloqueado</h2>
                <p>{{ $blockReason }}</p>
            </article>
        @else
            <article class="verification-card {{ $allowed ? 'verification-card--allowed' : 'verification-card--blocked' }}">
                <span>{{ $allowed ? 'Acesso permitido' : 'Acesso bloqueado' }}</span>
                <h2>{{ $member->name }}</h2>
                <p>{{ $member->membership_code }} · {{ $member->plan->name }} · {{ $member->category }}</p>
                <strong>{{ $allowed ? 'Liberado para entrada' : $blockReason }}</strong>
            </article>

            <div class="verification-grid">
                <article class="ops-panel">
                    <h2>Dados do associado</h2>
                    <div class="ops-row"><span>Status</span><strong>{{ $member->statusLabel() }}</strong></div>
                    <div class="ops-row"><span>Código da carteirinha</span><strong>{{ $cardCode }}</strong></div>
                    <div class="ops-row"><span>Emitida em</span><strong>{{ $member->card_issued_at?->format('d/m/Y') ?? 'Não informado' }}</strong></div>
                    <div class="ops-row"><span>Vencimento financeiro</span><strong>Dia {{ $member->dueDay() }}</strong></div>
                </article>

                <article class="ops-panel">
                    <h2>Dependentes ativos</h2>
                    @forelse($member->dependents as $dependent)
                        <div class="ops-row">
                            <span>{{ $dependent->name }} <small>{{ $dependent->relationship }}</small></span>
                            <strong>{{ $dependent->access_status === 'allowed' ? 'Liberado' : 'Bloqueado' }}</strong>
                        </div>
                    @empty
                        <p>Nenhum dependente ativo cadastrado.</p>
                    @endforelse
                </article>

                <article class="ops-panel ops-panel-wide">
                    <h2>Cobranças abertas</h2>
                    @forelse($openInvoices as $invoice)
                        <div class="ops-row">
                            <span>{{ $invoice->description }} <small>Vence em {{ $invoice->due_date->format('d/m/Y') }}</small></span>
                            <strong class="{{ $invoice->status === 'overdue' ? 'warn' : '' }}">{{ $invoice->statusLabel() }}</strong>
                        </div>
                    @empty
                        <p>Sem pendências abertas ou vencidas no momento da leitura.</p>
                    @endforelse
                </article>
            </div>
        @endif
    </section>
@endsection
