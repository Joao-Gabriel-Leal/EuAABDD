@extends('layouts.club', ['title' => 'Equipe | AABB Brasília'])

@section('content')
    <section class="team-header">
        <div>
            <p class="overline">Painel da equipe</p>
            <h1>Operação AABB Brasília</h1>
            <p>Secretaria, financeiro, reservas, convites, estoque e acesso em leitura simples.</p>
        </div>
        <a class="club-button club-button--yellow" href="/admin">Abrir CRUD Filament</a>
    </section>

    <section class="team-metrics">
        <article><span>Receitas</span><strong>R$ {{ number_format($income, 2, ',', '.') }}</strong></article>
        <article><span>Despesas</span><strong>R$ {{ number_format($expenses, 2, ',', '.') }}</strong></article>
        <article><span>Recebido</span><strong>R$ {{ number_format($paidAmount, 2, ',', '.') }}</strong></article>
        <article><span>Pendente</span><strong>R$ {{ number_format($pendingAmount, 2, ',', '.') }}</strong></article>
    </section>

    <section class="ops-grid">
        <article class="ops-panel">
            <h2>Associados</h2>
            @foreach($members as $member)
                <div class="ops-row">
                    <span>{{ $member->name }} <small>{{ $member->membership_code }}</small></span>
                    <strong>{{ $member->plan->name }}</strong>
                </div>
            @endforeach
        </article>

        <article class="ops-panel">
            <h2>Financeiro</h2>
            @foreach($invoices as $invoice)
                <div class="ops-row">
                    <span>{{ $invoice->member->name }} <small>{{ $invoice->description }}</small></span>
                    <strong class="{{ $invoice->status === 'paid' ? 'ok' : 'warn' }}">R$ {{ number_format($invoice->amount, 2, ',', '.') }}</strong>
                </div>
            @endforeach
        </article>

        <article class="ops-panel">
            <h2>Reservas</h2>
            @foreach($reservations as $reservation)
                <div class="ops-row">
                    <span>{{ $reservation->space->name }} <small>{{ $reservation->member->name }} · {{ $reservation->guests->count() }} convidados</small></span>
                    <strong>{{ $reservation->reservation_date->format('d/m') }}</strong>
                </div>
            @endforeach
        </article>

        <article class="ops-panel">
            <h2>Estoque</h2>
            @foreach($products as $product)
                <div class="ops-row">
                    <span>{{ $product->name }} <small>{{ $product->category }}</small></span>
                    <strong class="{{ $product->quantity < $product->minimum_quantity ? 'warn' : 'ok' }}">{{ $product->quantity }} {{ $product->unit }}</strong>
                </div>
            @endforeach
        </article>

        <article class="ops-panel">
            <h2>Propostas</h2>
            @foreach($proposals as $proposal)
                <div class="ops-row">
                    <span>{{ $proposal->name }} <small>{{ $proposal->email }}</small></span>
                    <strong>{{ $proposal->status }}</strong>
                </div>
            @endforeach
        </article>

        <article class="ops-panel">
            <h2>Portaria</h2>
            @foreach($accessLogs as $log)
                <div class="ops-row">
                    <span>{{ $log->person_name }} <small>{{ $log->gate }}</small></span>
                    <strong>{{ $log->checked_at->format('H:i') }}</strong>
                </div>
            @endforeach
        </article>
    </section>
@endsection
