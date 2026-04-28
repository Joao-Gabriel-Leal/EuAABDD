@extends('layouts.club', ['title' => 'Portal do Associado | AABB Brasilia'])

@section('content')
    @php
        $openInvoices = $member->invoices->whereIn('status', ['open', 'overdue', 'awaiting_review', 'pending']);
        $initialInvoice = $member->invoices->firstWhere('type', 'membership_initial');
        $isPendingSignup = $member->status === 'pending_payment';
        $clubInvitations = $member->invitations->where('type', 'club_access');
        $monthInvitations = $clubInvitations->filter(fn ($invitation) => $invitation->valid_for->isSameMonth(now()));
    @endphp

    <section class="portal-hero">
        <div>
            <p class="overline">Portal do associado</p>
            <h1>Ola, {{ $member->name }}.</h1>
            <p>Carteirinha, mensalidades, comprovantes, reservas, convites e dependentes em um so lugar.</p>
        </div>
        <button
            class="member-card-flip"
            type="button"
            aria-label="Virar carteirinha digital e mostrar QR Code"
            aria-pressed="false"
            data-card-flip
        >
            <span class="member-card-flip__inner">
                <span class="member-card member-card--front">
                    <span class="member-card__brand">AABB Brasilia</span>
                    <strong>{{ $member->membership_code }}</strong>
                    <span class="member-card__name">{{ $member->name }}</span>
                    <small>{{ $member->plan->name }} | {{ $member->category }}</small>
                    <em>Status {{ strtolower($member->statusLabel()) }} | vencimento dia {{ $member->dueDay() }}</em>
                    <span class="member-card__hint">Toque para ver o QR Code</span>
                </span>

                <span class="member-card member-card--back" aria-hidden="true">
                    <span class="member-card__brand">Validacao AABB</span>
                    <img src="{{ $cardQrCode }}" alt="QR Code de validacao da carteirinha de {{ $member->name }}">
                    <strong>{{ $cardCode }}</strong>
                    <small>Escaneie pela portaria</small>
                    <em>{{ $member->cardAccessAllowed() ? 'Acesso liberado em tempo real' : ($member->cardBlockReason() ?? 'Verificar secretaria') }}</em>
                </span>
            </span>
        </button>
    </section>

    @if($isPendingSignup)
        <section class="portal-grid portal-grid--single">
            <article class="portal-panel panel-wide portal-alert portal-alert--warning">
                <span>Aguardando pagamento da adesao</span>
                <h2>Sua conta ja foi criada. Falta pagar a primeira mensalidade para liberar a carteirinha.</h2>
                <p>Enquanto a cobranca inicial estiver aberta, o QR da carteirinha fica bloqueado na portaria e reservas/convites ficam travados.</p>
                @if($initialInvoice && $initialInvoice->isPayable())
                    <form method="POST" action="{{ route('portal.pay.demo', $initialInvoice) }}" class="inline-form portal-pay-demo">
                        @csrf
                        <span>Primeira mensalidade: R$ {{ number_format($initialInvoice->amount, 2, ',', '.') }}</span>
                        <button class="club-button club-button--yellow" type="submit">Pagar agora no demo</button>
                    </form>
                @endif
            </article>
        </section>
    @endif

    <section class="portal-grid">
        <article class="portal-panel panel-wide">
            <div class="panel-head">
                <h2>Financeiro</h2>
                <span>{{ $openInvoices->count() }} cobranca(s) aberta(s)</span>
            </div>
            <div class="invoice-list">
                @foreach($member->invoices->take(6) as $invoice)
                    <div class="invoice-row">
                        <div>
                            <strong>{{ $invoice->description }}</strong>
                            <small>
                                Vence em {{ $invoice->due_date->format('d/m/Y') }}
                                | {{ $invoice->payment_method ?? 'Boleto BRB / QR App' }}
                                | {{ $invoice->statusLabel() }}
                            </small>
                        </div>
                        <span>R$ {{ number_format($invoice->amount, 2, ',', '.') }}</span>
                        @if($invoice->type === 'membership_initial' && in_array($invoice->status, ['open', 'overdue', 'pending'], true))
                            <form method="POST" action="{{ route('portal.pay.demo', $invoice) }}" class="proof-form">
                                @csrf
                                <button class="mini-button" type="submit">Pagar demo e liberar carteirinha</button>
                            </form>
                        @elseif(in_array($invoice->status, ['open', 'overdue', 'pending'], true))
                            <form method="POST" action="{{ route('portal.pay', $invoice) }}" class="proof-form" enctype="multipart/form-data">
                                @csrf
                                <select name="payment_method" required>
                                    <option value="QR App AABB">QR App AABB</option>
                                    <option value="Boleto BRB">Boleto BRB</option>
                                    <option value="Debito em conta BRB">Debito em conta BRB</option>
                                    <option value="Cartao presencial">Cartao presencial</option>
                                </select>
                                <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf">
                                <button class="mini-button" type="submit">Enviar comprovante</button>
                            </form>
                        @elseif($invoice->status === 'awaiting_review')
                            <em>Em analise</em>
                        @else
                            <em>Pago</em>
                        @endif
                    </div>
                @endforeach
            </div>
        </article>

        <article class="portal-panel {{ $isPendingSignup ? 'portal-panel--locked' : '' }}">
            <h2>Dependentes</h2>
            @if($isPendingSignup)
                <p>Disponivel apos pagamento da primeira mensalidade.</p>
            @else
                <div class="simple-list">
                    @foreach($member->dependents as $dependent)
                        <span>
                            {{ $dependent->name }}
                            <small>{{ $dependent->relationship }} | {{ $dependent->is_free ? 'cortesia' : 'taxado' }}</small>
                        </span>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="portal-panel {{ $isPendingSignup ? 'portal-panel--locked' : '' }}">
            <h2>Convites do mes</h2>
            @if($isPendingSignup)
                <p>Convites liberados depois que a carteirinha estiver ativa.</p>
            @else
                <strong class="big-number">{{ $monthInvitations->count() }}/{{ $member->plan->included_guests }}</strong>
                <p>Excedente: R$ {{ number_format($member->plan->extra_guest_price, 2, ',', '.') }} por convidado.</p>
                <form method="POST" action="{{ route('portal.invitations.store') }}" class="stack-form compact-form">
                    @csrf
                    <input name="name" placeholder="Nome do convidado" required>
                    <input name="cpf" data-mask="cpf" inputmode="numeric" maxlength="14" placeholder="CPF opcional">
                    <input type="email" name="email" placeholder="E-mail opcional para enviar o convite">
                    <input type="date" name="valid_for" value="{{ now()->format('Y-m-d') }}" required>
                    <button class="club-button club-button--yellow" type="submit">Gerar convite</button>
                </form>

                <div class="invitation-wallet">
                    <h3>Convites gerados</h3>
                    @forelse($clubInvitations->take(8) as $invitation)
                        <article class="invitation-ticket invitation-ticket--{{ $invitation->status }}">
                            <div>
                                <span>{{ $invitation->guest?->name ?? 'Convidado' }}</span>
                                <strong>{{ $invitation->code }}</strong>
                                <small>
                                    Valido em {{ $invitation->valid_for->format('d/m/Y') }}
                                    | {{ $invitation->statusLabel() }}
                                    @if($invitation->sent_to_email)
                                        | enviado para {{ $invitation->sent_to_email }}
                                    @endif
                                </small>
                            </div>
                            <div class="invitation-ticket__actions">
                                <button
                                    class="mini-button mini-button--light"
                                    type="button"
                                    data-copy-text="{{ $invitation->code }}"
                                >
                                    Copiar codigo
                                </button>
                                <button
                                    class="mini-button"
                                    type="button"
                                    data-share-text="{{ $invitation->shareText() }}"
                                >
                                    Compartilhar
                                </button>
                            </div>
                        </article>
                    @empty
                        <p>Nenhum convite gerado neste mes ainda.</p>
                    @endforelse
                </div>
            @endif
        </article>
    </section>

    <section class="portal-grid">
        <article class="portal-panel panel-wide {{ $isPendingSignup ? 'portal-panel--locked' : '' }}">
            <div class="panel-head">
                <h2>Reservas</h2>
                <span>agenda com bloqueio de conflito</span>
            </div>

            @if($isPendingSignup)
                <p>Reservas de churrasqueira e espacos ficam disponiveis apos o pagamento inicial.</p>
            @else
                <div class="reservation-list">
                    @foreach($member->reservations as $reservation)
                        <div class="reservation-card">
                            <img src="{{ $reservation->space->image_url }}" alt="{{ $reservation->space->name }}">
                            <div>
                                <strong>{{ $reservation->space->name }}</strong>
                                <small>{{ $reservation->reservation_date->format('d/m/Y') }} | {{ $reservation->starts_at }} as {{ $reservation->ends_at }} | {{ $reservation->statusLabel() }}</small>
                                <p>{{ $reservation->guests->count() }} convidados | R$ {{ number_format($reservation->total_amount, 2, ',', '.') }}</p>
                                <form method="POST" action="{{ route('portal.guests.store', $reservation) }}" class="inline-form">
                                    @csrf
                                    <input name="name" placeholder="Nome do convidado" required>
                                    <input name="cpf" data-mask="cpf" inputmode="numeric" maxlength="14" placeholder="CPF opcional">
                                    <button class="mini-button" type="submit">Adicionar</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="portal-panel {{ $isPendingSignup ? 'portal-panel--locked' : '' }}">
            <h2>Nova reserva</h2>
            @if($isPendingSignup)
                <p>Pague a primeira mensalidade para desbloquear a agenda de churrasqueiras.</p>
            @else
                <form method="POST" action="{{ route('portal.reserve') }}" class="stack-form">
                    @csrf
                    <label>Espaco
                        <select name="reservable_space_id" data-calendar-space>
                            @foreach($spaces as $space)
                                <option value="{{ $space->id }}">{{ $space->name }} | R$ {{ number_format($space->base_price, 2, ',', '.') }}</option>
                            @endforeach
                        </select>
                    </label>
                    <input type="hidden" name="reservation_date" value="{{ now()->addWeek()->format('Y-m-d') }}" data-calendar-date-input required>

                    <div class="calendar-shell calendar-shell--portal" data-reservation-calendar data-calendar-mode="portal" data-availability-url="{{ route('reservations.availability') }}">
                        <div class="calendar-board calendar-board--compact">
                            <section class="calendar-card">
                                <div class="calendar-card__head">
                                    <button type="button" class="calendar-nav" data-calendar-prev aria-label="Mes anterior">&lsaquo;</button>
                                    <strong data-calendar-title>Carregando...</strong>
                                    <button type="button" class="calendar-nav" data-calendar-next aria-label="Proximo mes">&rsaquo;</button>
                                </div>
                                <div class="calendar-weekdays">
                                    <span>DOM</span><span>SEG</span><span>TER</span><span>QUA</span><span>QUI</span><span>SEX</span><span>SAB</span>
                                </div>
                                <div class="calendar-days" data-calendar-days></div>
                            </section>

                            <section class="calendar-card calendar-card--side">
                                <h3>Horario da reserva</h3>
                                <div class="calendar-slots" data-calendar-slots></div>
                                <div class="calendar-selected-summary" data-calendar-summary></div>
                            </section>
                        </div>
                    </div>

                    <button class="club-button club-button--blue" type="submit" data-calendar-submit>Reservar e gerar cobranca</button>
                </form>
            @endif
        </article>
    </section>
@endsection
