@php
    $editingSpace = $spaceEditor;
    $spaceFormAction = $editingSpace
        ? route('team.spaces.update', $editingSpace)
        : route('team.spaces.store');
    $spaceFormImageUrl = old(
        'image_url',
        $editingSpace && str_starts_with((string) $editingSpace->image_url, 'http')
            ? $editingSpace->image_url
            : '',
    );
@endphp

<section class="ops-grid ops-grid--inside">
    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Espacos do clube</h2>
            <span>cadastro e ativacao sem sair da agenda</span>
        </div>

        <div class="space-admin-layout">
            <section class="space-form-panel">
                <div class="panel-head">
                    <h3>{{ $editingSpace ? 'Editar espaco' : 'Novo espaco reservavel' }}</h3>
                    @if($editingSpace)
                        <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#reservas">Novo cadastro</a>
                    @endif
                </div>

                <form method="POST" action="{{ $spaceFormAction }}" class="stack-form" enctype="multipart/form-data">
                    @csrf
                    @if($editingSpace)
                        @method('PUT')
                    @endif

                    <div class="form-grid-2">
                        <label>Nome do espaco
                            <input name="name" value="{{ old('name', $editingSpace?->name) }}" placeholder="Churrasqueira Lago Norte" required>
                        </label>
                        <label>Tipo
                            <input name="type" value="{{ old('type', $editingSpace?->type ?? 'churrasqueira') }}" placeholder="churrasqueira, quadra, salao, evento" required>
                        </label>
                    </div>

                    <label>Localizacao
                        <input name="location" value="{{ old('location', $editingSpace?->location) }}" placeholder="Ala esportiva, bosque, area da piscina..." required>
                    </label>

                    <div class="form-grid-2">
                        <label>Capacidade
                            <input name="capacity" type="number" min="1" value="{{ old('capacity', $editingSpace?->capacity ?? 20) }}" required>
                        </label>
                        <label>Preco base
                            <input name="base_price" type="number" min="0" step="0.01" value="{{ old('base_price', $editingSpace?->base_price ?? 0) }}" required>
                        </label>
                    </div>

                    <div class="form-grid-2">
                        <label>Inicio padrao
                            <input name="starts_at" type="time" value="{{ old('starts_at', $editingSpace?->startsAt() ?? '12:00') }}" required>
                        </label>
                        <label>Fim padrao
                            <input name="ends_at" type="time" value="{{ old('ends_at', $editingSpace?->endsAt() ?? '18:00') }}" required>
                        </label>
                    </div>

                    <div class="form-grid-2">
                        <label>Convidados inclusos
                            <input name="included_guests" type="number" min="0" value="{{ old('included_guests', $editingSpace?->includedGuests() ?? 4) }}" required>
                        </label>
                        <label class="check-row space-check-row">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingSpace?->is_active ?? true))>
                            Publicar no portal e no calendario
                        </label>
                    </div>

                    <div class="form-grid-2">
                        <label>Imagem por URL externa
                            <input name="image_url" type="url" value="{{ $spaceFormImageUrl }}" placeholder="https://exemplo.com/espaco.jpg">
                        </label>
                        <label>Upload da imagem
                            <input name="image_file" type="file" accept=".jpg,.jpeg,.png,.webp">
                        </label>
                    </div>

                    @if($editingSpace?->image_url)
                        <div class="space-current-image">
                            <img src="{{ $editingSpace->image_url }}" alt="Imagem atual de {{ $editingSpace->name }}">
                            <p>Imagem atual pronta para home, portal e painel.</p>
                        </div>
                    @endif

                    <button class="club-button club-button--blue" type="submit">
                        {{ $editingSpace ? 'Salvar espaco' : 'Cadastrar espaco' }}
                    </button>
                </form>
            </section>

            <section class="space-list-panel">
                <div class="panel-head">
                    <h3>Lista operacional</h3>
                    <span>{{ $managedReservationSpaces->count() }} espaco(s)</span>
                </div>

                <div class="space-list">
                    @forelse($managedReservationSpaces as $space)
                        <article class="space-card">
                            <img class="space-card__image" src="{{ $space->image_url }}" alt="{{ $space->name }}">

                            <div class="space-card__content">
                                <div class="space-card__head">
                                    <div>
                                        <strong>{{ $space->name }}</strong>
                                        <small>{{ ucfirst($space->type) }} | {{ $space->location }}</small>
                                    </div>
                                    <span class="stock-badge stock-badge--{{ $space->is_active ? 'success' : 'muted' }}">
                                        {{ $space->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </div>

                                <div class="space-meta">
                                    <span><b>{{ $space->capacity }}</b> pessoas</span>
                                    <span><b>R$ {{ number_format((float) $space->base_price, 2, ',', '.') }}</b> base</span>
                                    <span><b>{{ $space->includedGuests() }}</b> convidados inclusos</span>
                                </div>

                                <p>Agenda padrao das {{ $space->startsAt() }} as {{ $space->endsAt() }}.</p>

                                <div class="space-actions">
                                    <a class="mini-button mini-button--light" href="{{ route('team.dashboard', ['space' => $space->id]) }}#reservas">Editar</a>

                                    <form method="POST" action="{{ route('team.spaces.toggle', $space) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="mini-button {{ $space->is_active ? '' : 'mini-button--light' }}" type="submit">
                                            {{ $space->is_active ? 'Desativar' : 'Ativar' }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p>Nenhum espaco cadastrado ainda.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </article>

    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Agenda de reservas</h2>
            <span>calendario operacional</span>
        </div>

        @if($reservationSpaces->isNotEmpty())
            <div class="calendar-shell" data-reservation-calendar data-calendar-mode="team" data-availability-url="{{ route('reservations.availability') }}">
                <div class="calendar-toolbar">
                    <label>Espaco
                        <select data-calendar-space>
                            @foreach($reservationSpaces as $space)
                                <option value="{{ $space->id }}">{{ $space->name }} | R$ {{ number_format((float) $space->base_price, 2, ',', '.') }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="calendar-board">
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
        @else
            <p>Ative pelo menos um espaco para liberar a agenda de reservas.</p>
        @endif
    </article>

    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Reservas recentes</h2>
            <span>{{ $reservations->count() }} registro(s)</span>
        </div>

        @forelse($reservations as $reservation)
            <div class="ops-row">
                <span>
                    {{ $reservation->space?->name ?? 'Espaco removido' }}
                    <small>{{ $reservation->member?->name ?? 'Associado removido' }} | {{ $reservation->reservation_date->format('d/m/Y') }} | {{ $reservation->guests->count() }} convidados | {{ $reservation->statusLabel() }}</small>
                </span>
                <strong>R$ {{ number_format((float) $reservation->total_amount, 2, ',', '.') }}</strong>
            </div>
        @empty
            <p>Nenhuma reserva recente encontrada.</p>
        @endforelse
    </article>

    <article class="ops-panel">
        <h2>Convites</h2>
        @forelse($invitations as $invitation)
            <div class="ops-row">
                <span>{{ $invitation->guest?->name ?? 'Convite avulso' }} <small>{{ $invitation->member?->name ?? 'Associado removido' }} | {{ $invitation->code }} | {{ $invitation->valid_for->format('d/m/Y') }}</small></span>
                <strong>{{ $invitation->statusLabel() }}</strong>
            </div>
        @empty
            <p>Nenhum convite recente.</p>
        @endforelse
    </article>

    <article class="ops-panel">
        <h2>Convidados</h2>
        @forelse($guests as $guest)
            <div class="ops-row">
                <span>{{ $guest->name }} <small>{{ $guest->member?->name ?? 'Sem titular' }} | {{ $guest->reservation?->space?->name ?? 'Acesso clube' }}</small></span>
                <strong>{{ $guest->is_extra ? 'Excedente' : 'Cota' }}</strong>
            </div>
        @empty
            <p>Nenhum convidado recente.</p>
        @endforelse
    </article>
</section>
