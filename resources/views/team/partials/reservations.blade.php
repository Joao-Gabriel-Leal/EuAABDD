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
    $activeManagedSpaces = $managedReservationSpaces->where('is_active', true)->count();
    $activeSpaceTypes = $managedSpaceTypes->where('is_active', true)->count();
@endphp

<section class="reservation-admin-shell">
    <header class="reservation-admin-hero">
        <div>
            <p class="overline">Reservas</p>
            <h2>Painel de espacos e agenda</h2>
            <span>Cadastre espacos, posicione pins e consulte a agenda sem misturar tudo na mesma area.</span>
        </div>
        <div class="reservation-admin-actions">
            <a class="club-button club-button--blue" href="{{ route('team.dashboard', ['create' => 'space']) }}#reservas">Novo espaco</a>
            <a class="mini-button mini-button--light" href="{{ route('team.dashboard', ['create' => 'space_type']) }}#reservas">Tipo de pin</a>
        </div>
    </header>

    <div class="reservation-admin-stats">
        <article>
            <strong>{{ $managedReservationSpaces->count() }}</strong>
            <span>espacos cadastrados</span>
        </article>
        <article>
            <strong>{{ $activeManagedSpaces }}</strong>
            <span>ativos no portal</span>
        </article>
        <article>
            <strong>{{ $activeSpaceTypes }}</strong>
            <span>tipos de pin ativos</span>
        </article>
        <article>
            <strong>{{ $reservations->count() }}</strong>
            <span>reservas recentes</span>
        </article>
    </div>

    @if($showSpaceForm)
        <article class="ops-panel ops-panel-wide reservation-space-editor">
            <div class="panel-head">
                <div>
                    <h2>{{ $editingSpace ? 'Editar espaco' : 'Novo espaco' }}</h2>
                    <span>Preencha o basico, depois clique no mapa para posicionar o pin.</span>
                </div>
                <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#reservas">Voltar ao mapa</a>
            </div>

            <form method="POST" action="{{ $spaceFormAction }}" class="space-editor-form" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_team_form" value="space">
                @if($editingSpace)
                    @method('PUT')
                @endif

                <section class="space-editor-card">
                    <div class="space-editor-card__title">
                        <strong>Dados essenciais</strong>
                        <span>O minimo para identificar e vender a reserva.</span>
                    </div>

                    <div class="form-grid-2">
                        <label>Nome do espaco
                            <input name="name" value="{{ old('name', $editingSpace?->name) }}" placeholder="Churrasqueira Lago Norte" required>
                        </label>
                        <label>Tipo do pin
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

                    <label class="check-row space-check-row">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingSpace?->is_active ?? true))>
                        Publicar no portal e no calendario
                    </label>
                </section>

                <section class="space-editor-card space-editor-card--map">
                    <div class="space-editor-card__title">
                        <strong>Pin no mapa</strong>
                        <span>Clique exatamente onde esse espaco fica na planta.</span>
                    </div>

                    <input name="map_x" type="hidden" value="{{ $spaceMapX }}" data-map-x-input required>
                    <input name="map_y" type="hidden" value="{{ $spaceMapY }}" data-map-y-input required>

                    <section class="map-pin-editor" data-map-picker>
                        <div class="map-pin-editor__head">
                            <div>
                                <h4>Posicao do pin</h4>
                                <span>O ponto salvo aparece no portal e no painel da equipe.</span>
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

                    <label>Referencia no mapa
                        <input name="map_note" value="{{ old('map_note', $editingSpace?->mapNote()) }}" placeholder="Ao lado da piscina, perto do bosque...">
                    </label>
                </section>

                <details class="space-editor-advanced">
                    <summary>
                        <span>Horario, convidados e imagem</span>
                        <b>configuracoes extras</b>
                    </summary>
                    <div class="space-editor-advanced__body">
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
                    </div>
                </details>

                <div class="form-actions reservation-editor-actions">
                    <button class="club-button club-button--blue" type="submit">
                        {{ $editingSpace ? 'Salvar espaco' : 'Cadastrar espaco' }}
                    </button>
                    <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#reservas">Cancelar</a>
                </div>
            </form>
        </article>
    @endif

    <article class="ops-panel ops-panel-wide reservation-map-workbench" data-reservation-map-upload data-team-space-map>
        <div class="panel-head">
            <div>
                <h2>Espacos no mapa</h2>
                <span>Selecione um pin ou um item da lista para editar sem perder contexto.</span>
            </div>
            @unless($showSpaceForm)
                <a class="club-button club-button--blue" href="{{ route('team.dashboard', ['create' => 'space']) }}#reservas">Cadastrar espaco</a>
            @endunless
        </div>

        <div class="reservation-map-toolbar">
            <form method="POST" action="{{ route('team.reservation-map.store') }}" enctype="multipart/form-data" class="reservation-map-upload-form">
                @csrf
                <label>Trocar imagem da planta
                    <input name="reservation_map" type="file" accept=".jpg,.jpeg,.png,.webp" required>
                </label>
                <button class="mini-button" type="submit">Atualizar planta</button>
            </form>
        </div>

        <div class="reservation-map-board">
            <div class="reservation-map-manager__preview reservation-map-manager__preview--pins">
                <img src="{{ $reservationMapUrl }}" alt="Planta atual do clube para reservas">
                @foreach($managedReservationSpaces as $space)
                    <button
                        type="button"
                        class="reservation-map-pin team-space-pin {{ $space->is_active ? '' : 'is-muted' }} {{ $loop->first ? 'is-active' : '' }}"
                        style="left: {{ $space->mapX() }}%; top: {{ $space->mapY() }}%; --pin-color: {{ $space->pinColor() }};"
                        data-team-space-pin
                        data-space-id="{{ $space->id }}"
                        aria-label="Ver detalhes de {{ $space->name }}"
                    >
                        {{ $loop->iteration }}
                    </button>
                @endforeach
            </div>

            <aside class="space-map-sidebar">
                <div class="space-map-sidebar__head">
                    <strong>Espacos</strong>
                    <span>{{ $activeManagedSpaces }} ativo(s)</span>
                </div>

                <div class="space-map-list">
                    @forelse($managedReservationSpaces as $space)
                        <button
                            type="button"
                            class="space-map-list__item {{ $loop->first ? 'is-active' : '' }}"
                            data-team-space-list-item
                            data-space-id="{{ $space->id }}"
                        >
                            <i style="--pin-color: {{ $space->pinColor() }}">{{ $loop->iteration }}</i>
                            <span>
                                <strong>{{ $space->name }}</strong>
                                <small>{{ $space->typeName() }} | {{ $space->location }}</small>
                            </span>
                            <b>{{ $space->is_active ? 'Ativo' : 'Inativo' }}</b>
                        </button>
                    @empty
                        <div class="admin-empty-state">
                            <strong>Nenhum espaco cadastrado.</strong>
                            <a class="mini-button" href="{{ route('team.dashboard', ['create' => 'space']) }}#reservas">Cadastrar primeiro espaco</a>
                        </div>
                    @endforelse
                </div>

                <div class="team-space-details">
                    <div class="team-space-details__empty" data-team-space-empty @if($managedReservationSpaces->isNotEmpty()) hidden @endif>
                        <strong>Selecione um espaco</strong>
                        <span>Os dados principais aparecem aqui.</span>
                    </div>
                    @foreach($managedReservationSpaces as $space)
                        <article class="team-space-detail" data-team-space-detail="{{ $space->id }}" @if(! $loop->first) hidden @endif>
                            <img src="{{ $space->image_url }}" alt="{{ $space->name }}">
                            <div class="team-space-detail__body">
                                <div class="team-space-detail__head">
                                    <span class="space-type-swatch" style="--pin-color: {{ $space->pinColor() }}"></span>
                                    <div>
                                        <strong>{{ $space->name }}</strong>
                                        <small>{{ $space->mapNote() ?: $space->location }}</small>
                                    </div>
                                    <b class="stock-badge stock-badge--{{ $space->is_active ? 'success' : 'muted' }}">{{ $space->is_active ? 'Ativo' : 'Inativo' }}</b>
                                </div>
                                <div class="team-space-detail__meta">
                                    <span><b>{{ $space->capacity }}</b> pessoas</span>
                                    <span><b>R$ {{ number_format((float) $space->base_price, 2, ',', '.') }}</b> base</span>
                                    <span><b>{{ $space->startsAt() }}-{{ $space->endsAt() }}</b> horario</span>
                                    <span><b>{{ $space->mapX() }}%, {{ $space->mapY() }}%</b> pin</span>
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
            </aside>
        </div>

        <details class="pin-type-drawer" @if($showSpaceTypeForm) open @endif>
            <summary>
                <span>Tipos e cores dos pins</span>
                <b>{{ $managedSpaceTypes->count() }} tipo(s)</b>
            </summary>

            <div class="pin-type-drawer__body">
                <div class="space-type-manager__head">
                    <div>
                        <h3>Catalogo de tipos</h3>
                        <p>A cor escolhida aqui vale para todos os espacos daquele tipo.</p>
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
            </div>
        </details>
    </article>

    <article class="ops-panel ops-panel-wide reservation-calendar-panel">
        <div class="panel-head">
            <div>
                <h2>Agenda</h2>
                <span>Escolha um espaco e veja disponibilidade por dia.</span>
            </div>
        </div>

        @if($reservationSpaces->isNotEmpty())
            <div class="calendar-shell" data-reservation-calendar data-calendar-mode="team" data-availability-url="{{ route('reservations.availability', [], false) }}">
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
                        <h3>Resumo do dia</h3>
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

    <section class="reservation-admin-ledger">
        <article class="ops-panel ops-panel-wide">
            <div class="panel-head">
                <div>
                    <h2>Reservas recentes</h2>
                    <span>{{ $reservations->count() }} registro(s)</span>
                </div>
            </div>

            <div class="reservation-recent-list">
                @forelse($reservations as $reservation)
                    @php
                        $pendingGuests = $reservation->guests->where('status', 'awaiting_payment')->count();
                    @endphp
                    <article class="reservation-recent-card">
                        <span class="stock-badge stock-badge--{{ $reservation->status === 'confirmed' ? 'success' : ($reservation->status === 'cancelled' ? 'danger' : 'warning') }}">
                            {{ $reservation->statusLabel() }}
                        </span>
                        <div>
                            <strong>{{ $reservation->space?->name ?? 'Espaco removido' }}</strong>
                            <small>{{ $reservation->member?->name ?? 'Associado removido' }} | {{ $reservation->reservation_date->format('d/m/Y') }}</small>
                            <small>{{ $reservation->guests->count() }} convidado(s), {{ $pendingGuests }} pendente(s)</small>
                        </div>
                        <b>R$ {{ number_format((float) $reservation->total_amount, 2, ',', '.') }}</b>
                    </article>
                @empty
                    <p>Nenhuma reserva recente encontrada.</p>
                @endforelse
            </div>
        </article>

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
</section>
