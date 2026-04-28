@extends('layouts.club', ['title' => 'Portal do Associado | AABB Brasília'])

@section('content')
    <section class="portal-hero">
        <div>
            <p class="overline">Portal do associado</p>
            <h1>Olá, {{ $member->name }}.</h1>
            <p>Carteirinha, mensalidades, reservas, convites e dependentes em um só lugar.</p>
        </div>
        <article class="member-card">
            <span>AABB Brasília</span>
            <strong>{{ $member->membership_code }}</strong>
            <p>{{ $member->plan->name }} · {{ $member->category }}</p>
            <small>Status {{ $member->status === 'active' ? 'ativo' : $member->status }}</small>
        </article>
    </section>

    <section class="portal-grid">
        <article class="portal-panel panel-wide">
            <div class="panel-head">
                <h2>Financeiro</h2>
                <span>{{ $member->invoices->where('status', 'pending')->count() }} pendente(s)</span>
            </div>
            <div class="invoice-list">
                @foreach($member->invoices->take(5) as $invoice)
                    <div class="invoice-row">
                        <div>
                            <strong>{{ $invoice->description }}</strong>
                            <small>Vence em {{ $invoice->due_date->format('d/m/Y') }} · {{ $invoice->payment_method ?? 'pagamento digital' }}</small>
                        </div>
                        <span>R$ {{ number_format($invoice->amount, 2, ',', '.') }}</span>
                        @if($invoice->status === 'pending')
                            <form method="POST" action="{{ route('portal.pay', $invoice) }}">
                                @csrf
                                <button class="mini-button" type="submit">Pagar</button>
                            </form>
                        @else
                            <em>Pago</em>
                        @endif
                    </div>
                @endforeach
            </div>
        </article>

        <article class="portal-panel">
            <h2>Dependentes</h2>
            <div class="simple-list">
                @foreach($member->dependents as $dependent)
                    <span>{{ $dependent->name }} <small>{{ $dependent->relationship }}</small></span>
                @endforeach
            </div>
        </article>

        <article class="portal-panel">
            <h2>Convites do mês</h2>
            <strong class="big-number">{{ $member->invitations->where('is_extra', false)->count() }}/{{ $member->plan->included_guests }}</strong>
            <p>Excedente: R$ {{ number_format($member->plan->extra_guest_price, 2, ',', '.') }} por convidado.</p>
        </article>
    </section>

    <section class="portal-grid">
        <article class="portal-panel panel-wide">
            <div class="panel-head">
                <h2>Reservas</h2>
                <span>churrasqueiras e espaços</span>
            </div>

            <div class="reservation-list">
                @foreach($member->reservations as $reservation)
                    <div class="reservation-card">
                        <img src="{{ $reservation->space->image_url }}" alt="{{ $reservation->space->name }}">
                        <div>
                            <strong>{{ $reservation->space->name }}</strong>
                            <small>{{ $reservation->reservation_date->format('d/m/Y') }} · {{ $reservation->starts_at }} às {{ $reservation->ends_at }}</small>
                            <p>{{ $reservation->guests->count() }} convidados · R$ {{ number_format($reservation->total_amount, 2, ',', '.') }}</p>
                            <form method="POST" action="{{ route('portal.guests.store', $reservation) }}" class="inline-form">
                                @csrf
                                <input name="name" placeholder="Nome do convidado" required>
                                <input name="cpf" placeholder="CPF opcional">
                                <button class="mini-button" type="submit">Adicionar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="portal-panel">
            <h2>Nova reserva</h2>
            <form method="POST" action="{{ route('portal.reserve') }}" class="stack-form">
                @csrf
                <label>Espaço
                    <select name="reservable_space_id">
                        @foreach($spaces as $space)
                            <option value="{{ $space->id }}">{{ $space->name }} · R$ {{ number_format($space->base_price, 2, ',', '.') }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Data <input type="date" name="reservation_date" value="{{ now()->addWeek()->format('Y-m-d') }}" required></label>
                <button class="club-button club-button--blue" type="submit">Reservar</button>
            </form>
        </article>
    </section>
@endsection
