@extends('layouts.club', ['title' => 'Portal do Associado | AABB Brasilia'])

@section('content')
    @php
        $openInvoices = $member->invoices->whereIn('status', ['open', 'overdue', 'awaiting_review', 'pending']);
        $initialInvoice = $member->invoices->firstWhere('type', 'membership_initial');
        $isPendingSignup = $member->status === 'pending_payment';
        $clubInvitations = $member->invitations->where('type', 'club_access');
        $monthInvitations = $clubInvitations->filter(fn ($invitation) => $invitation->valid_for->isSameMonth(now()));
        $visibleInvitations = $member->invitations->whereIn('type', ['club_access', 'reservation_guest']);
        $activeReservations = $member->reservations
            ->filter(fn ($reservation) => ! in_array($reservation->status, ['cancelled', 'rejected'], true))
            ->sortBy('reservation_date')
            ->values();
        $futureReservations = $activeReservations
            ->filter(fn ($reservation) => $reservation->reservation_date->isToday() || $reservation->reservation_date->isFuture())
            ->values();
        $pastReservations = $activeReservations
            ->filter(fn ($reservation) => $reservation->reservation_date->isPast() && ! $reservation->reservation_date->isToday())
            ->sortByDesc('reservation_date')
            ->values();
    @endphp

    <section class="portal-hero">
        <div>
            <p class="overline">Portal do associado</p>
            <h1>Ola, {{ $member->name }}.</h1>
            <p>Reservas, convidados e pagamentos AABB em um so lugar.</p>
        </div>
        @if(\App\Support\Modules::enabled('member_card'))
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
        @endif
    </section>

    @if($isPendingSignup)
        <section class="portal-status-strip">
            <article class="portal-alert portal-alert--warning portal-alert--compact">
                <div>
                    <span>Aguardando pagamento da adesao</span>
                    <strong>Sua conta ja foi criada. Falta pagar a primeira mensalidade para liberar tudo.</strong>
                    <p>Carteirinha, reservas e convites ficam bloqueados ate a baixa da cobranca inicial.</p>
                </div>
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

    <section class="portal-workspace" data-tab-workspace data-default-tab="reservas">
        <div class="portal-tab-nav" role="tablist" aria-label="Areas do portal do associado">
            <button
                type="button"
                role="tab"
                class="portal-tab-card"
                id="portal-tab-financeiro"
                aria-controls="portal-panel-financeiro"
                aria-selected="false"
                tabindex="-1"
                data-tab-target="financeiro"
            >
                <span>Pagamentos</span>
                <strong>Cobrancas AABB</strong>
                <small>{{ $openInvoices->count() }} aberta(s)</small>
            </button>
            <button
                type="button"
                role="tab"
                class="portal-tab-card is-active"
                id="portal-tab-reservas"
                aria-controls="portal-panel-reservas"
                aria-selected="true"
                tabindex="0"
                data-tab-target="reservas"
            >
                <span>Clube</span>
                <strong>Reservas</strong>
                <small>{{ $futureReservations->count() }} futura(s)</small>
            </button>
            @if(\App\Support\Modules::enabled('portal_club_invitations'))
            <button
                type="button"
                role="tab"
                class="portal-tab-card"
                id="portal-tab-convites"
                aria-controls="portal-panel-convites"
                aria-selected="false"
                tabindex="-1"
                data-tab-target="convites"
            >
                <span>Acesso</span>
                <strong>Convites</strong>
                <small>{{ $monthInvitations->count() }}/{{ $member->plan->included_guests }} no mes</small>
            </button>
            @endif
            @if(\App\Support\Modules::enabled('portal_dependents'))
            <button
                type="button"
                role="tab"
                class="portal-tab-card"
                id="portal-tab-familia"
                aria-controls="portal-panel-familia"
                aria-selected="false"
                tabindex="-1"
                data-tab-target="familia"
            >
                <span>Plano</span>
                <strong>Familia</strong>
                <small>{{ $member->dependents->where('status', 'active')->count() }}/{{ $member->plan->included_dependents }} cortesia</small>
            </button>
            @endif
        </div>

        <section
            class="portal-tab-panel"
            id="portal-panel-financeiro"
            role="tabpanel"
            aria-labelledby="portal-tab-financeiro"
            data-tab-panel="financeiro"
            hidden
        >
            <article class="portal-panel portal-panel--tab">
                <div class="panel-head">
                    <h2>Financeiro</h2>
                    <span>{{ $openInvoices->count() }} cobranca(s) aberta(s)</span>
                </div>
                <div class="invoice-list">
                    @forelse($member->invoices->take(6) as $invoice)
                        <div class="invoice-row">
                            <div>
                                <strong>{{ $invoice->description }}</strong>
                                <small>
                                    Vence em {{ $invoice->due_date->format('d/m/Y') }}
                                    | {{ $invoice->payment_method ?? 'Boleto Banco do Brasil / QR App' }}
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
                                        <option value="Boleto Banco do Brasil">Boleto Banco do Brasil</option>
                                        <option value="Debito em conta Banco do Brasil">Debito em conta Banco do Brasil</option>
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
                    @empty
                        <p>Nenhuma cobranca encontrada.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section
            class="portal-tab-panel"
            id="portal-panel-reservas"
            role="tabpanel"
            aria-labelledby="portal-tab-reservas"
            data-tab-panel="reservas"
        >
            <div class="portal-tab-stack">
                <article class="portal-panel portal-panel--tab reservation-booking-panel {{ $isPendingSignup ? 'portal-panel--locked' : '' }}">
                    <div class="panel-head">
                        <h2>Nova reserva</h2>
                        <span>agenda com bloqueio de conflito</span>
                    </div>
                    @if($isPendingSignup)
                        <p>Pague a primeira mensalidade para desbloquear a agenda de churrasqueiras.</p>
                    @elseif($spaces->isEmpty())
                        <p>Nenhum espaco ativo para reserva no momento.</p>
                    @else
                        @php
                            $spaceTypeFilters = $spaces
                                ->map(fn ($space) => [
                                    'slug' => $space->typeSlug(),
                                    'name' => $space->typeName(),
                                    'color' => $space->pinColor(),
                                ])
                                ->unique('slug')
                                ->values();
                        @endphp
                        <form method="POST" action="{{ route('portal.reserve') }}" class="stack-form reservation-builder" data-reservation-builder>
                            @csrf
                            <input type="hidden" name="reservation_date" value="{{ now()->addWeek()->format('Y-m-d') }}" data-calendar-date-input required>

                            <div class="reservation-builder-grid reservation-builder-grid--schedule-first">
                                <div class="calendar-shell calendar-shell--portal" data-reservation-calendar data-calendar-mode="portal" data-availability-url="{{ route('reservations.availability', [], false) }}">
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

                                <section class="reservation-map-card reservation-map-card--portal">
                                    <div class="reservation-map-card__main">
                                        <div class="reservation-map-card__head">
                                            <div>
                                                <span>Escolha no mapa</span>
                                                <strong>Espacos cadastrados</strong>
                                            </div>
                                            <small>{{ $spaces->count() }} opcoes ativas</small>
                                        </div>
                                        <div class="reservation-map-filters" aria-label="Filtrar espacos por tipo">
                                            <button type="button" class="is-active" data-space-type-filter="all">Todos</button>
                                            @foreach($spaceTypeFilters as $typeFilter)
                                                <button type="button" style="--pin-color: {{ $typeFilter['color'] }};" data-space-type-filter="{{ $typeFilter['slug'] }}">
                                                    <span></span>{{ $typeFilter['name'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                        <div class="reservation-map" data-space-map>
                                            <img src="{{ $reservationMapUrl }}" alt="Planta do clube para escolha do espaco">
                                            @foreach($spaces as $space)
                                                <button
                                                    type="button"
                                                    class="reservation-map-pin"
                                                    style="left: {{ $space->mapX() }}%; top: {{ $space->mapY() }}%; --pin-color: {{ $space->pinColor() }};"
                                                    data-space-map-pin
                                                    data-space-id="{{ $space->id }}"
                                                    data-space-type="{{ $space->typeSlug() }}"
                                                    aria-label="Selecionar {{ $space->name }}"
                                                >
                                                    {{ $loop->iteration }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="reservation-map-legend" aria-label="Lista de espacos no mapa">
                                        @foreach($spaces as $space)
                                            <button type="button" data-space-map-pin data-space-id="{{ $space->id }}" data-space-type="{{ $space->typeSlug() }}">
                                                <span class="reservation-map-legend__number" style="--pin-color: {{ $space->pinColor() }}">{{ $loop->iteration }}</span>
                                                <span>
                                                    <strong>{{ $space->name }}</strong>
                                                    <small><i style="--pin-color: {{ $space->pinColor() }}"></i>{{ $space->typeName() }} | {{ $space->mapNote() ?: $space->location }}</small>
                                                    <em>{{ $space->capacity }} pessoas | R$ {{ number_format((float) $space->base_price, 2, ',', '.') }} aluguel | R$ {{ number_format($space->guestPrice(), 2, ',', '.') }} convidado</em>
                                                </span>
                                                <b>Ativa</b>
                                            </button>
                                        @endforeach
                                    </div>
                                </section>

                                <section class="reservation-form-card reservation-form-card--space">
                                    <label>Espaco
                                        <select name="reservable_space_id" data-calendar-space>
                                            @foreach($spaces as $space)
                                                <option
                                                    value="{{ $space->id }}"
                                                    data-base-price="{{ (float) $space->base_price }}"
                                                    data-guest-price="{{ $space->guestPrice() }}"
                                                    data-capacity="{{ $space->capacity }}"
                                                    data-space-name="{{ $space->name }}"
                                                    data-space-type="{{ $space->typeSlug() }}"
                                                >
                                                    {{ $space->name }} | R$ {{ number_format($space->base_price, 2, ',', '.') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <div class="reservation-payment-modes">
                                        <label>
                                            <input type="radio" name="payment_mode" value="associado_paga" checked data-payment-mode>
                                            <span>Pago tudo junto</span>
                                            <small>O aluguel e os convidados ficam na cobranca da reserva.</small>
                                        </label>
                                        <label>
                                            <input type="radio" name="payment_mode" value="rateio_email" data-payment-mode>
                                            <span>Rateio por convidado</span>
                                            <small>O aluguel fica comigo; convidados entram por cobranca individual.</small>
                                        </label>
                                    </div>
                                </section>
                            </div>

                            <section class="reservation-cost-summary" data-reservation-cost-summary>
                                <div>
                                    <span>Aluguel</span>
                                    <strong data-summary-base>R$ 0,00</strong>
                                </div>
                                <div>
                                    <span>Convidados</span>
                                    <strong data-summary-guests>0 x R$ 14,00</strong>
                                </div>
                                <div>
                                    <span>Associado paga agora</span>
                                    <strong data-summary-member>Total R$ 0,00</strong>
                                </div>
                                <div>
                                    <span>Rateado</span>
                                    <strong data-summary-split>R$ 0,00</strong>
                                </div>
                            </section>

                            <button class="club-button club-button--blue" type="submit" data-calendar-submit>Reservar e gerar cobranca</button>
                        </form>
                    @endif
                </article>

                <article class="portal-panel portal-panel--tab {{ $isPendingSignup ? 'portal-panel--locked' : '' }}">
                    <div class="panel-head">
                        <h2>Minhas reservas</h2>
                        <span>{{ $futureReservations->count() }} futura(s)</span>
                    </div>

                    @if($isPendingSignup)
                        <p>Reservas de churrasqueira e espacos ficam disponiveis apos o pagamento inicial.</p>
                    @else
                        <div class="reservation-list reservation-list--clean" data-reservation-list="future">
                            @forelse($futureReservations as $reservation)
                                <article class="reservation-card reservation-card--clean">
                                    <img src="{{ $reservation->space->image_url }}" alt="{{ $reservation->space->name }}">
                                    <div class="reservation-card__body">
                                        <div>
                                            <strong>{{ $reservation->space->name }}</strong>
                                            <small>{{ $reservation->space->location }}</small>
                                        </div>
                                        <dl class="reservation-card__meta">
                                            <div>
                                                <dt>Data</dt>
                                                <dd>{{ $reservation->reservation_date->format('d/m/Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt>Horario</dt>
                                                <dd>{{ $reservation->starts_at }} as {{ $reservation->ends_at }}</dd>
                                            </div>
                                            <div>
                                                <dt>Status</dt>
                                                <dd>{{ $reservation->statusLabel() }}</dd>
                                            </div>
                                            <div>
                                                <dt>Total</dt>
                                                <dd>R$ {{ number_format($reservation->total_amount, 2, ',', '.') }}</dd>
                                            </div>
                                        </dl>
                                        <button class="mini-button" type="button" data-open-reservation-modal="{{ $reservation->id }}">Abrir reserva</button>
                                    </div>
                                </article>
                            @empty
                                <p>Nenhuma reserva futura registrada.</p>
                            @endforelse
                        </div>

                        @if($pastReservations->isNotEmpty())
                            <div class="reservation-history">
                                <button class="mini-button mini-button--light" type="button" data-toggle-past-reservations>
                                    Ver passadas
                                </button>
                                <div class="reservation-list reservation-list--clean" data-past-reservations hidden>
                                    @foreach($pastReservations as $reservation)
                                        <article class="reservation-card reservation-card--clean reservation-card--past">
                                            <img src="{{ $reservation->space->image_url }}" alt="{{ $reservation->space->name }}">
                                            <div class="reservation-card__body">
                                                <div>
                                                    <strong>{{ $reservation->space->name }}</strong>
                                                    <small>{{ $reservation->reservation_date->format('d/m/Y') }} | {{ $reservation->starts_at }} as {{ $reservation->ends_at }}</small>
                                                </div>
                                                <dl class="reservation-card__meta">
                                                    <div>
                                                        <dt>Status</dt>
                                                        <dd>{{ $reservation->statusLabel() }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Convidados</dt>
                                                        <dd>{{ $reservation->guests->count() }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt>Total</dt>
                                                        <dd>R$ {{ number_format($reservation->total_amount, 2, ',', '.') }}</dd>
                                                    </div>
                                                </dl>
                                                <button class="mini-button mini-button--light" type="button" data-open-reservation-modal="{{ $reservation->id }}">Abrir reserva</button>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </article>

                @if(! $isPendingSignup && $activeReservations->isNotEmpty())
                    @foreach($activeReservations as $reservation)
                        @php
                            $paymentMode = $reservation->invoice?->metadata['pagamento'] ?? 'associado_paga';
                            $canEditReservationGuests = ! in_array($reservation->status, ['cancelled', 'rejected'], true)
                                && ($reservation->reservation_date->isToday() || $reservation->reservation_date->isFuture());
                            $guestCapacityLeft = max(0, (int) $reservation->space->capacity - $reservation->guests->count());
                        @endphp
                        <div class="reservation-modal" data-reservation-modal="{{ $reservation->id }}" role="dialog" aria-modal="true" aria-labelledby="reservation-modal-title-{{ $reservation->id }}" hidden>
                            <button class="reservation-modal__backdrop" type="button" data-close-reservation-modal aria-label="Fechar reserva"></button>
                            <aside class="reservation-modal__panel">
                                <header class="reservation-modal__head">
                                    <div>
                                        <span>Reserva</span>
                                        <h3 id="reservation-modal-title-{{ $reservation->id }}">{{ $reservation->space->name }}</h3>
                                    </div>
                                    <button class="calendar-nav" type="button" data-close-reservation-modal aria-label="Fechar">&times;</button>
                                </header>

                                <div class="reservation-modal__hero">
                                    <img src="{{ $reservation->space->image_url }}" alt="{{ $reservation->space->name }}">
                                    <dl>
                                        <div>
                                            <dt>Data</dt>
                                            <dd>{{ $reservation->reservation_date->format('d/m/Y') }}</dd>
                                        </div>
                                        <div>
                                            <dt>Horario</dt>
                                            <dd>{{ $reservation->starts_at }} as {{ $reservation->ends_at }}</dd>
                                        </div>
                                        <div>
                                            <dt>Status</dt>
                                            <dd>{{ $reservation->statusLabel() }}</dd>
                                        </div>
                                        <div>
                                            <dt>Total</dt>
                                            <dd>R$ {{ number_format($reservation->total_amount, 2, ',', '.') }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <section class="reservation-modal__section">
                                    <div class="panel-head panel-head--compact">
                                        <h4>Cobranca</h4>
                                        <span>{{ $paymentMode === 'rateio_email' ? 'rateio por convidado' : 'pago pelo associado' }}</span>
                                    </div>
                                    <dl class="reservation-modal__finance">
                                        <div>
                                            <dt>Fatura</dt>
                                            <dd>{{ $reservation->invoice?->number ?? 'Sem fatura' }}</dd>
                                        </div>
                                        <div>
                                            <dt>Status</dt>
                                            <dd>{{ $reservation->invoice?->statusLabel() ?? 'Nao vinculada' }}</dd>
                                        </div>
                                        <div>
                                            <dt>Convidados</dt>
                                            <dd>{{ $reservation->guests->count() }}/{{ $reservation->space->capacity }}</dd>
                                        </div>
                                    </dl>
                                </section>

                                @if($canEditReservationGuests)
                                    <section class="reservation-modal__section">
                                        <div class="panel-head panel-head--compact">
                                            <h4>Adicionar convidados</h4>
                                            <span>{{ $guestCapacityLeft }} vaga(s)</span>
                                        </div>
                                        <form method="POST" action="{{ route('portal.reservation-guests.store', $reservation) }}" class="reservation-modal-form">
                                            @csrf
                                            <input name="name" placeholder="Nome do convidado" required>
                                            <input name="cpf" data-mask="cpf" inputmode="numeric" maxlength="14" placeholder="CPF opcional">
                                            <input name="contact" placeholder="E-mail ou celular com DDD" required>
                                            <button class="mini-button" type="submit">Adicionar</button>
                                        </form>
                                        <div class="reservation-import-row">
                                            <a class="mini-button mini-button--light" href="{{ route('portal.reservation-guests.template') }}">Baixar modelo CSV</a>
                                            <form method="POST" action="{{ route('portal.reservation-guests.import', $reservation) }}" enctype="multipart/form-data" class="reservation-import-form">
                                                @csrf
                                                <input type="file" name="guest_file" accept=".csv,text/csv,text/plain" required>
                                                <button class="mini-button mini-button--light" type="submit">Importar CSV</button>
                                            </form>
                                        </div>
                                    </section>
                                @endif

                                <section class="reservation-modal__section">
                                    <div class="panel-head panel-head--compact">
                                        <h4>Lista de convidados</h4>
                                        <span>{{ $reservation->guests->count() }} pessoa(s)</span>
                                    </div>
                                    <div class="reservation-modal-guests">
                                        @forelse($reservation->guests as $guest)
                                            @php
                                                $invitation = $guest->invitation;
                                                $guestStatus = match ($guest->status) {
                                                    'confirmed' => 'Liberado',
                                                    'awaiting_payment' => 'Aguardando pagamento',
                                                    'used' => 'Usado',
                                                    default => ucfirst(str_replace('_', ' ', (string) $guest->status)),
                                                };
                                                $guestStatusValue = (string) $guest->status;
                                                $invitationStatus = (string) ($invitation?->status ?? '');
                                                $invoiceStatus = (string) ($invitation?->invoice?->status ?? '');
                                                $guestTone = 'danger';

                                                if ($guestStatusValue === 'used' || $invitationStatus === 'used') {
                                                    $guestTone = 'neutral';
                                                } elseif (
                                                    in_array($guestStatusValue, ['cancelled', 'rejected', 'blocked'], true)
                                                    || in_array($invitationStatus, ['cancelled', 'rejected', 'blocked'], true)
                                                    || in_array($invoiceStatus, ['overdue', 'cancelled'], true)
                                                    || ($invitationStatus === 'available' && $reservation->status !== 'confirmed')
                                                ) {
                                                    $guestTone = 'danger';
                                                } elseif (
                                                    $guestStatusValue === 'awaiting_payment'
                                                    || in_array($invitationStatus, ['payment_pending', 'extra_pending'], true)
                                                    || in_array($invoiceStatus, ['open', 'pending', 'awaiting_review'], true)
                                                ) {
                                                    $guestTone = 'warning';
                                                } elseif ($guestStatusValue === 'confirmed' || $invitationStatus === 'available') {
                                                    $guestTone = 'success';
                                                }

                                                $invitationTone = match ($invitationStatus) {
                                                    'available' => $reservation->status === 'confirmed' ? 'success' : 'danger',
                                                    'payment_pending', 'extra_pending' => 'warning',
                                                    'used' => 'neutral',
                                                    default => $invitation ? 'danger' : 'danger',
                                                };
                                                $invoiceTone = match ($invoiceStatus) {
                                                    'paid' => 'success',
                                                    'open', 'pending', 'awaiting_review' => 'warning',
                                                    'overdue', 'cancelled' => 'danger',
                                                    default => $invoiceStatus === '' ? 'neutral' : 'danger',
                                                };
                                                $guestCanEdit = $canEditReservationGuests && $guest->status !== 'used' && ! $guest->checked_in_at && $invitation?->status !== 'used';
                                                $guestContact = $guest->email ?: $guest->phone;
                                            @endphp
                                            <article class="reservation-modal-guest">
                                                <form method="POST" action="{{ route('portal.reservation-guests.update', [$reservation, $guest]) }}" class="reservation-modal-guest__form">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input name="name" value="{{ $guest->name }}" placeholder="Nome" required {{ $guestCanEdit ? '' : 'disabled' }}>
                                                    <input name="cpf" value="{{ $guest->cpf }}" data-mask="cpf" inputmode="numeric" maxlength="14" placeholder="CPF opcional" {{ $guestCanEdit ? '' : 'disabled' }}>
                                                    <input name="contact" value="{{ $guestContact }}" placeholder="E-mail ou celular" required {{ $guestCanEdit ? '' : 'disabled' }}>
                                                    @if($guestCanEdit)
                                                        <button class="mini-button mini-button--light" type="submit">Salvar</button>
                                                    @endif
                                                </form>
                                                <div class="reservation-modal-guest__meta">
                                                    <span class="reservation-status-badge reservation-status-badge--{{ $guestTone }}">{{ $guestStatus }}</span>
                                                    @if($invitation)
                                                        <small class="reservation-status-detail reservation-status-detail--{{ $invitationTone }}">Codigo {{ $invitation->code }} | {{ $invitation->statusLabel() }}</small>
                                                        @if($invitation->invoice && $invitation->invoice->status !== 'paid')
                                                            <small class="reservation-status-detail reservation-status-detail--{{ $invoiceTone }}">{{ $invitation->invoice->number }} | {{ $invitation->invoice->statusLabel() }}</small>
                                                        @endif
                                                    @else
                                                        <small class="reservation-status-detail reservation-status-detail--danger">Convite nao vinculado</small>
                                                    @endif
                                                </div>
                                                <div class="reservation-modal-guest__actions">
                                                    @if($invitation)
                                                        <button class="mini-button mini-button--light" type="button" data-copy-text="{{ $invitation->code }}">Copiar codigo</button>
                                                        <button class="mini-button mini-button--light" type="button" data-share-text="{{ $invitation->shareText() }}">Compartilhar</button>
                                                        @if($invitation->whatsappUrl())
                                                            <a class="mini-button" href="{{ $invitation->whatsappUrl() }}" target="_blank" rel="noopener">WhatsApp</a>
                                                        @endif
                                                    @endif
                                                    @if($guestCanEdit)
                                                        <form method="POST" action="{{ route('portal.reservation-guests.destroy', [$reservation, $guest]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="mini-button mini-button--light" type="submit">Remover</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </article>
                                        @empty
                                            <p>Nenhum convidado adicionado ainda.</p>
                                        @endforelse
                                    </div>
                                </section>
                            </aside>
                        </div>
                    @endforeach
                @endif
            </div>
        </section>

        @if(\App\Support\Modules::enabled('portal_club_invitations'))
        <section
            class="portal-tab-panel"
            id="portal-panel-convites"
            role="tabpanel"
            aria-labelledby="portal-tab-convites"
            data-tab-panel="convites"
            hidden
        >
            <article class="portal-panel portal-panel--tab {{ $isPendingSignup ? 'portal-panel--locked' : '' }}">
                <div class="panel-head">
                    <h2>Convites</h2>
                    @if(! $isPendingSignup)
                        <span>{{ $monthInvitations->count() }}/{{ $member->plan->included_guests }} no mes</span>
                    @endif
                </div>
                @if($isPendingSignup)
                    <p>Convites liberados depois que a carteirinha estiver ativa.</p>
                @else
                    <div class="portal-compact-grid">
                        <div>
                            <strong class="big-number">{{ $monthInvitations->count() }}/{{ $member->plan->included_guests }}</strong>
                            <p>Excedente: R$ {{ number_format($member->plan->extra_guest_price, 2, ',', '.') }} por convidado.</p>
                        </div>
                        <form method="POST" action="{{ route('portal.invitations.store') }}" class="stack-form compact-form">
                            @csrf
                            <input name="name" placeholder="Nome do convidado" required>
                            <input name="cpf" data-mask="cpf" inputmode="numeric" maxlength="14" placeholder="CPF opcional">
                            <input type="email" name="email" placeholder="E-mail opcional para enviar o convite">
                            <input type="date" name="valid_for" value="{{ now()->format('Y-m-d') }}" required>
                            <button class="club-button club-button--yellow" type="submit">Gerar convite</button>
                        </form>
                    </div>

                    <div class="invitation-wallet">
                        <h3>Convites gerados</h3>
                        @forelse($visibleInvitations->take(8) as $invitation)
                            <article class="invitation-ticket invitation-ticket--{{ $invitation->status }}">
                                <div>
                                    <span>{{ $invitation->guest?->name ?? 'Convidado' }}</span>
                                    <strong>{{ $invitation->code }}</strong>
                                    <small>
                                        Valido em {{ $invitation->valid_for->format('d/m/Y') }}
                                        | {{ $invitation->statusLabel() }}
                                        @if($invitation->type === 'reservation_guest')
                                            | Reserva: {{ $invitation->guest?->reservation?->space?->name ?? 'espaco reservado' }}
                                        @endif
                                        @if($invitation->sent_to_email)
                                            | enviado para {{ $invitation->sent_to_email }}
                                        @elseif($invitation->sent_to_phone)
                                            | WhatsApp {{ $invitation->sent_to_phone }}
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
                            <p>Nenhum convite gerado ainda.</p>
                        @endforelse
                    </div>
                @endif
            </article>
        </section>
        @endif

        @if(\App\Support\Modules::enabled('portal_dependents'))
        <section
            class="portal-tab-panel"
            id="portal-panel-familia"
            role="tabpanel"
            aria-labelledby="portal-tab-familia"
            data-tab-panel="familia"
            hidden
        >
            <article class="portal-panel portal-panel--tab {{ $isPendingSignup ? 'portal-panel--locked' : '' }}">
                <div class="panel-head">
                    <h2>Familia</h2>
                    @if(! $isPendingSignup)
                        <span>{{ $member->dependents->where('status', 'active')->count() }}/{{ $member->plan->included_dependents }} cortesia</span>
                    @endif
                </div>
                @if($isPendingSignup)
                    <p>Disponivel apos pagamento da primeira mensalidade.</p>
                @else
                    <div class="portal-compact-grid">
                        <div>
                            <h3>Dependentes cadastrados</h3>
                            <div class="simple-list">
                                @forelse($member->dependents as $dependent)
                                    <span>
                                        {{ $dependent->name }}
                                        <small>
                                            {{ $dependent->relationship ?: 'Dependente' }}
                                            | {{ $dependent->is_free ? 'cortesia' : 'R$ '.number_format((float) $dependent->monthly_fee, 2, ',', '.').'/mes' }}
                                        </small>
                                    </span>
                                @empty
                                    <p>Nenhum dependente cadastrado ainda.</p>
                                @endforelse
                            </div>
                        </div>

                        @php
                            $nextDependentIsFree = $member->dependents->where('status', 'active')->count() < (int) $member->plan->included_dependents;
                            $nextDependentFee = (float) $member->plan->dependent_extra_price;
                        @endphp

                        <form method="POST" action="{{ route('portal.dependents.store') }}" class="stack-form compact-form">
                            @csrf
                            <input name="name" value="{{ old('name') }}" placeholder="Nome do dependente" required>
                            <input name="cpf" value="{{ old('cpf') }}" data-mask="cpf" inputmode="numeric" maxlength="14" placeholder="CPF opcional">
                            <input type="date" name="birthdate" value="{{ old('birthdate') }}">
                            <input name="relationship" value="{{ old('relationship') }}" placeholder="Parentesco">
                            <p>
                                {{ $nextDependentIsFree
                                    ? 'Proximo dependente entra dentro da cota do plano.'
                                    : 'Proximo dependente tera mensalidade extra de R$ '.number_format($nextDependentFee, 2, ',', '.').'.' }}
                            </p>
                            <button class="club-button club-button--blue" type="submit">Cadastrar dependente</button>
                        </form>
                    </div>
                @endif
            </article>
        </section>
        @endif
    </section>
@endsection
