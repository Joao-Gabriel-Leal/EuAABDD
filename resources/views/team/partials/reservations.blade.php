<section class="ops-grid ops-grid--inside">
    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Agenda de reservas</h2>
            <span>calendario operacional</span>
        </div>

        <div class="calendar-shell" data-reservation-calendar data-calendar-mode="team" data-availability-url="{{ route('reservations.availability') }}">
            <div class="calendar-toolbar">
                <label>Espaco
                    <select data-calendar-space>
                        @foreach($reservationSpaces as $space)
                            <option value="{{ $space->id }}">{{ $space->name }} | R$ {{ number_format($space->base_price, 2, ',', '.') }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="calendar-board">
                <section class="calendar-card">
                    <div class="calendar-card__head">
                        <button type="button" class="calendar-nav" data-calendar-prev aria-label="Mes anterior">‹</button>
                        <strong data-calendar-title>Carregando...</strong>
                        <button type="button" class="calendar-nav" data-calendar-next aria-label="Proximo mes">›</button>
                    </div>
                    <div class="calendar-weekdays">
                        <span>DOM</span><span>SEG</span><span>TER</span><span>QUA</span><span>QUI</span><span>SEX</span><span>SAB</span>
                    </div>
                    <div class="calendar-days" data-calendar-days></div>
                    <p class="calendar-help">Dias com ponto azul ja possuem reserva ativa para esse espaco.</p>
                </section>

                <section class="calendar-card calendar-card--side">
                    <h3>Horarios disponiveis</h3>
                    <div class="calendar-slots" data-calendar-slots></div>
                    <div class="calendar-selected-summary" data-calendar-summary></div>
                    <div class="calendar-day-reservations" data-calendar-reservations></div>
                </section>
            </div>
        </div>
    </article>

    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Reservas recentes</h2>
            <span>{{ $reservations->count() }} registro(s)</span>
        </div>
        @foreach($reservations as $reservation)
            <div class="ops-row">
                <span>
                    {{ $reservation->space->name }}
                    <small>{{ $reservation->member->name }} | {{ $reservation->reservation_date->format('d/m/Y') }} | {{ $reservation->guests->count() }} convidados | {{ $reservation->statusLabel() }}</small>
                </span>
                <strong>R$ {{ number_format($reservation->total_amount, 2, ',', '.') }}</strong>
            </div>
        @endforeach
    </article>

    <article class="ops-panel">
        <h2>Convites</h2>
        @foreach($invitations as $invitation)
            <div class="ops-row">
                <span>{{ $invitation->guest?->name ?? 'Convite avulso' }} <small>{{ $invitation->member->name }} | {{ $invitation->code }} | {{ $invitation->valid_for->format('d/m/Y') }}</small></span>
                <strong>{{ $invitation->statusLabel() }}</strong>
            </div>
        @endforeach
    </article>

    <article class="ops-panel">
        <h2>Convidados</h2>
        @foreach($guests as $guest)
            <div class="ops-row">
                <span>{{ $guest->name }} <small>{{ $guest->member?->name ?? 'Sem titular' }} | {{ $guest->reservation?->space?->name ?? 'Acesso clube' }}</small></span>
                <strong>{{ $guest->is_extra ? 'Excedente' : 'Cota' }}</strong>
            </div>
        @endforeach
    </article>
</section>
