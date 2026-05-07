@php
    $editingSpace = $spaceEditor;
    $editingSpaceType = $spaceTypeEditor;
    $showSpaceForm = $editingSpace || request('create') === 'space' || old('_team_form') === 'space';
    $showSpaceTypeForm = $editingSpaceType || request('create') === 'space_type' || old('_team_form') === 'space_type';
    $spaceFormAction = $editingSpace
        ? route('team.spaces.update', $editingSpace)
        : route('team.spaces.store');
    $spaceTypeFormAction = $editingSpaceType
        ? route('team.space-types.update', $editingSpaceType)
        : route('team.space-types.store');
    $spaceFormImageUrl = old(
        'image_url',
        $editingSpace && str_starts_with((string) $editingSpace->image_url, 'http')
            ? $editingSpace->image_url
            : '',
    );
    $spaceMapX = (int) old('map_x', $editingSpace?->mapX() ?? 50);
    $spaceMapY = (int) old('map_y', $editingSpace?->mapY() ?? 50);
    $spaceTypeOptions = $managedSpaceTypes
        ->filter(fn ($type) => $type->is_active || $editingSpace?->reservable_space_type_id === $type->id)
        ->values();
    $selectedSpaceTypeId = (int) old(
        'reservable_space_type_id',
        $editingSpace?->reservable_space_type_id ?? $spaceTypeOptions->first()?->id,
    );
    $selectedSpaceType = $spaceTypeOptions->firstWhere('id', $selectedSpaceTypeId) ?? $spaceTypeOptions->first();
    $selectedPinColor = $selectedSpaceType?->pin_color ?? '#e5163d';
@endphp

<section class="ops-grid ops-grid--inside">
    <article class="ops-panel ops-panel-wide">
        <div class="admin-section-head">
            <div>
                <h2>Espacos do clube</h2>
                <span>{{ $managedReservationSpaces->count() }} espaco(s) cadastrado(s)</span>
            </div>
            <a class="club-button club-button--blue" href="{{ route('team.dashboard', ['create' => 'space']) }}#reservas">Cadastrar espaco</a>
        </div>

        <section class="space-type-manager">
            <div class="space-type-manager__head">
                <div>
                    <p class="overline">Tipos e pins</p>
                    <h3>Catalogo de tipos de espaco</h3>
                    <p>Defina a cor do pin uma vez e use nos espacos cadastrados.</p>
                </div>
                <a class="mini-button" href="{{ route('team.dashboard', ['create' => 'space_type']) }}#reservas">Cadastrar tipo</a>
            </div>

            @if($showSpaceTypeForm)
                <form method="POST" action="{{ $spaceTypeFormAction }}" class="space-type-form" data-space-type-form>
                    @csrf
                    <input type="hidden" name="_team_form" value="space_type">
                    @if($editingSpaceType)
                        @method('PUT')
                    @endif

                    <label>Nome do tipo
                        <input name="name" value="{{ old('name', $editingSpaceType?->name) }}" placeholder="Salao de festa" required>
                    </label>
                    <label>Identificador
                        <input name="slug" value="{{ old('slug', $editingSpaceType?->slug) }}" placeholder="salao-de-festa">
                    </label>
                    <label>Cor do pin
                        <input name="pin_color" type="color" value="{{ old('pin_color', $editingSpaceType?->pin_color ?? '#e5163d') }}" data-pin-color-input required>
                    </label>
                    <div class="space-type-palette" aria-label="Cores rapidas">
                        @foreach(['#e65a24', '#d89b12', '#0ea5c6', '#12845b', '#7c3aed', '#e5163d'] as $color)
                            <button type="button" style="--pin-color: {{ $color }};" data-pin-color-choice="{{ $color }}" aria-label="Usar cor {{ $color }}"></button>
                        @endforeach
                    </div>
                    <div class="space-type-pin-preview">
                        <span class="reservation-map-pin reservation-map-pin--preview" style="--pin-color: {{ old('pin_color', $editingSpaceType?->pin_color ?? '#e5163d') }};" data-pin-preview>1</span>
                    </div>
                    <label class="check-row space-check-row">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingSpaceType?->is_active ?? true))>
                        Disponivel para novos espacos
                    </label>
                    <div class="form-actions">
                        <button class="mini-button" type="submit">{{ $editingSpaceType ? 'Salvar tipo' : 'Cadastrar tipo' }}</button>
                        <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#reservas">Cancelar</a>
                    </div>
                </form>
            @endif

            <div class="space-type-list">
                @foreach($managedSpaceTypes as $type)
                    <article class="space-type-chip">
                        <span class="space-type-swatch" style="--pin-color: {{ $type->pin_color }}"></span>
                        <span class="space-type-chip__text">
                            <strong>{{ $type->name }}</strong>
                            <small>{{ $type->spaces_count }} espaco(s)</small>
                        </span>
                        <b>{{ $type->is_active ? 'Ativo' : 'Inativo' }}</b>
                        <div class="space-type-chip__actions">
                            <a class="mini-button mini-button--light" href="{{ route('team.dashboard', ['space_type' => $type->id]) }}#reservas">Editar</a>
                            <form method="POST" action="{{ route('team.space-types.toggle', $type) }}">
                                @csrf
                                @method('PATCH')
                                <button class="mini-button {{ $type->is_active ? '' : 'mini-button--light' }}" type="submit">
                                    {{ $type->is_active ? 'Pausar' : 'Ativar' }}
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="reservation-map-manager reservation-map-manager--team" data-reservation-map-upload data-team-space-map>
            <div class="reservation-map-manager__tools">
                <div>
                    <p class="overline">Mapa de reservas</p>
                    <h3>Planta do clube</h3>
                    <p>Use os pins para localizar, editar ou desativar cada espaco.</p>
                </div>
                <form method="POST" action="{{ route('team.reservation-map.store') }}" enctype="multipart/form-data" class="reservation-map-upload-form">
                    @csrf
                    <label>Imagem da planta
                        <input name="reservation_map" type="file" accept=".jpg,.jpeg,.png,.webp" required>
                    </label>
                    <button class="mini-button" type="submit">Atualizar planta</button>
                </form>
            </div>
            <div class="reservation-map-manager__preview reservation-map-manager__preview--pins">
                <img src="{{ $reservationMapUrl }}" alt="Planta atual do clube para reservas">
                @foreach($managedReservationSpaces as $space)
                    <button
                        type="button"
                        class="reservation-map-pin team-space-pin {{ $space->is_active ? '' : 'is-muted' }}"
                        style="left: {{ $space->mapX() }}%; top: {{ $space->mapY() }}%; --pin-color: {{ $space->pinColor() }};"
                        data-team-space-pin
                        data-space-id="{{ $space->id }}"
                        aria-label="Ver detalhes de {{ $space->name }}"
                    >
                        {{ $loop->iteration }}
                    </button>
                @endforeach
            </div>
            <div class="team-space-details">
                <div class="team-space-details__empty" data-team-space-empty>
                    <strong>Clique em um pin</strong>
                    <span>Os dados, edicao e status do espaco aparecem aqui.</span>
                </div>
                @foreach($managedReservationSpaces as $space)
                    <article class="team-space-detail" data-team-space-detail="{{ $space->id }}" hidden>
                        <img src="{{ $space->image_url }}" alt="{{ $space->name }}">
                        <div class="team-space-detail__body">
                            <div class="team-space-detail__head">
                                <span class="space-type-swatch" style="--pin-color: {{ $space->pinColor() }}"></span>
                                <div>
                                    <strong>{{ $space->name }}</strong>
                                    <small>{{ $space->typeName() }} | {{ $space->location }}</small>
                                </div>
                                <b class="stock-badge stock-badge--{{ $space->is_active ? 'success' : 'muted' }}">{{ $space->is_active ? 'Ativo' : 'Inativo' }}</b>
                            </div>
                            <div class="team-space-detail__meta">
                                <span><b>{{ $space->capacity }}</b> pessoas</span>
                                <span><b>R$ {{ number_format((float) $space->base_price, 2, ',', '.') }}</b> base</span>
                                <span><b>R$ {{ number_format($space->guestPrice(), 2, ',', '.') }}</b> convidado</span>
                                <span><b>{{ $space->includedGuests() }}</b> inclusos</span>
                                <span><b>{{ $space->startsAt() }}</b> as {{ $space->endsAt() }}</span>
                                <span><b>{{ $space->mapX() }}%, {{ $space->mapY() }}%</b> mapa</span>
                            </div>
                            <div class="admin-record-card__actions">
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
                @endforeach
            </div>
        </section>

        @if($showSpaceForm)
            <section class="admin-crud-panel">
                <div class="panel-head">
                    <h3>{{ $editingSpace ? 'Editar espaco' : 'Novo espaco reservavel' }}</h3>
                    <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#reservas">Cancelar</a>
                </div>

                <form method="POST" action="{{ $spaceFormAction }}" class="stack-form" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_team_form" value="space">
                    @if($editingSpace)
                        @method('PUT')
                    @endif

                    <div class="form-grid-2">
                        <label>Nome do espaco
                            <input name="name" value="{{ old('name', $editingSpace?->name) }}" placeholder="Churrasqueira Lago Norte" required>
                        </label>
                        <label>Tipo
                            <select name="reservable_space_type_id" data-space-type-select required>
                                @foreach($spaceTypeOptions as $type)
                                    <option
                                        value="{{ $type->id }}"
                                        data-space-type-slug="{{ $type->slug }}"
                                        data-pin-color="{{ $type->pin_color }}"
                                        @selected($selectedSpaceTypeId === $type->id)
                                    >
                                        {{ $type->name }}{{ $type->is_active ? '' : ' (inativo)' }}
                                    </option>
                                @endforeach
                            </select>
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
                        <label>Valor por convidado
                            <input name="guest_price" type="number" min="0" step="0.01" value="{{ old('guest_price', $editingSpace?->guestPrice() ?? 14) }}" required>
                        </label>
                    </div>

                    <input name="map_x" type="hidden" value="{{ $spaceMapX }}" data-map-x-input required>
                    <input name="map_y" type="hidden" value="{{ $spaceMapY }}" data-map-y-input required>

                    <div class="form-grid-2">
                        <label>Referencia no mapa
                            <input name="map_note" value="{{ old('map_note', $editingSpace?->mapNote()) }}" placeholder="Ao lado da piscina, perto do bosque...">
                        </label>
                        <label class="check-row space-check-row">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingSpace?->is_active ?? true))>
                            Publicar no portal e no calendario
                        </label>
                    </div>

                    <section class="map-pin-editor" data-map-picker>
                        <div class="map-pin-editor__head">
                            <div>
                                <h4>Posicao do pin na planta</h4>
                                <span>Clique no ponto exato do espaco. O pin fica responsivo no portal.</span>
                            </div>
                            <strong data-map-position-label>{{ $spaceMapX }}%, {{ $spaceMapY }}%</strong>
                        </div>
                        <div class="map-pin-editor__canvas">
                            <img src="{{ $reservationMapUrl }}" alt="Clique na planta para posicionar o pin do espaco">
                            <button
                                type="button"
                                class="reservation-map-pin map-pin-editor__pin"
                                style="left: {{ $spaceMapX }}%; top: {{ $spaceMapY }}%; --pin-color: {{ $selectedPinColor }};"
                                data-map-picker-pin
                                aria-label="Pin do espaco no mapa"
                            >
                                PIN
                            </button>
                        </div>
                    </section>

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

                    <div class="form-actions">
                        <button class="club-button club-button--blue" type="submit">
                            {{ $editingSpace ? 'Salvar espaco' : 'Cadastrar espaco' }}
                        </button>
                        <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#reservas">Cancelar</a>
                    </div>
                </form>
            </section>
        @endif

        <details class="ops-collapsible spaces-fallback">
            <summary>
                <span>Ver lista completa de espacos</span>
                <b>{{ $managedReservationSpaces->count() }} cadastrado(s)</b>
            </summary>
            <div class="admin-list admin-list--spaces">
            @forelse($managedReservationSpaces as $space)
                <article class="admin-record-card admin-record-card--media">
                    <img class="admin-record-card__image" src="{{ $space->image_url }}" alt="{{ $space->name }}">

                    <div class="admin-record-card__body">
                        <div class="admin-record-card__head">
                            <div>
                                <strong>{{ $space->name }}</strong>
                                <small><span class="space-type-swatch space-type-swatch--inline" style="--pin-color: {{ $space->pinColor() }}"></span>{{ $space->typeName() }} | {{ $space->location }}</small>
                            </div>
                            <span class="stock-badge stock-badge--{{ $space->is_active ? 'success' : 'muted' }}">
                                {{ $space->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>

                        <div class="admin-record-card__meta">
                            <span><b>{{ $space->capacity }}</b> pessoas</span>
                            <span><b>R$ {{ number_format((float) $space->base_price, 2, ',', '.') }}</b> base</span>
                            <span><b>R$ {{ number_format($space->guestPrice(), 2, ',', '.') }}</b> por convidado</span>
                            <span><b>{{ $space->includedGuests() }}</b> convidados inclusos</span>
                            <span><b>{{ $space->startsAt() }}</b> as {{ $space->endsAt() }}</span>
                            <span><b>{{ $space->mapX() }}%, {{ $space->mapY() }}%</b> mapa</span>
                        </div>

                        <div class="admin-record-card__actions">
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
                <div class="admin-empty-state">
                    <strong>Nenhum espaco cadastrado ainda.</strong>
                    <a class="mini-button" href="{{ route('team.dashboard', ['create' => 'space']) }}#reservas">Cadastrar primeiro espaco</a>
                </div>
            @endforelse
            </div>
        </details>
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

    <details class="ops-panel ops-panel-wide ops-collapsible">
        <summary>
            <span>Reservas recentes</span>
            <b>{{ $reservations->count() }} registro(s)</b>
        </summary>

        @forelse($reservations as $reservation)
            <div class="ops-row">
                <span>
                    {{ $reservation->space?->name ?? 'Espaco removido' }}
                    @php
                        $pendingGuests = $reservation->guests->where('status', 'awaiting_payment')->count();
                    @endphp
                    <small>{{ $reservation->member?->name ?? 'Associado removido' }} | {{ $reservation->reservation_date->format('d/m/Y') }} | {{ $reservation->guests->count() }} convidados | {{ $pendingGuests }} pendente(s) | {{ $reservation->statusLabel() }}</small>
                    @if($reservation->guests->isNotEmpty())
                        <small>Portaria: {{ $reservation->guests->map(fn ($guest) => $guest->name.' - '.($guest->invitation?->statusLabel() ?? ucfirst((string) $guest->status)))->join('; ') }}</small>
                    @endif
                </span>
                <strong>R$ {{ number_format((float) $reservation->total_amount, 2, ',', '.') }}</strong>
            </div>
        @empty
            <p>Nenhuma reserva recente encontrada.</p>
        @endforelse
    </details>

    <details class="ops-panel ops-collapsible">
        <summary>
            <span>Convites</span>
            <b>{{ $invitations->count() }} registro(s)</b>
        </summary>
        @forelse($invitations as $invitation)
            <div class="ops-row">
                <span>{{ $invitation->guest?->name ?? 'Convite avulso' }} <small>{{ $invitation->member?->name ?? 'Associado removido' }} | {{ $invitation->code }} | {{ $invitation->valid_for->format('d/m/Y') }}</small></span>
                <strong>{{ $invitation->statusLabel() }}</strong>
            </div>
        @empty
            <p>Nenhum convite recente.</p>
        @endforelse
    </details>

    <details class="ops-panel ops-collapsible">
        <summary>
            <span>Convidados</span>
            <b>{{ $guests->count() }} registro(s)</b>
        </summary>
        @forelse($guests as $guest)
            <div class="ops-row">
                <span>{{ $guest->name }} <small>{{ $guest->member?->name ?? 'Sem titular' }} | {{ $guest->reservation?->space?->name ?? 'Acesso clube' }}</small></span>
                <strong>{{ $guest->is_extra ? 'Excedente' : 'Cota' }}</strong>
            </div>
        @empty
            <p>Nenhum convidado recente.</p>
        @endforelse
    </details>
</section>
