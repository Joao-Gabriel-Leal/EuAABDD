@php
    $editingAnnouncement = $announcementEditor;
    $editingBenefit = $benefitEditor;
    $showAnnouncementForm = $editingAnnouncement || request('create') === 'announcement' || old('_team_form') === 'announcement';
    $showBenefitForm = $editingBenefit || request('create') === 'benefit' || old('_team_form') === 'benefit';
    $announcementFormAction = $editingAnnouncement
        ? route('team.announcements.update', $editingAnnouncement)
        : route('team.announcements.store');
    $benefitFormAction = $editingBenefit
        ? route('team.benefits.update', $editingBenefit)
        : route('team.benefits.store');
@endphp

<section class="ops-grid ops-grid--inside">
    <article class="ops-panel">
        <div class="admin-section-head">
            <div>
                <h2>Comunicados</h2>
                <span>{{ $announcements->count() }} registro(s)</span>
            </div>
            <a class="club-button club-button--blue" href="{{ route('team.dashboard', ['create' => 'announcement']) }}#conteudo">Novo comunicado</a>
        </div>

        @if($showAnnouncementForm)
            <section class="admin-crud-panel">
                <div class="panel-head">
                    <h3>{{ $editingAnnouncement ? 'Editar comunicado' : 'Novo comunicado' }}</h3>
                    <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#conteudo">Cancelar</a>
                </div>

                <form method="POST" action="{{ $announcementFormAction }}" class="stack-form compact-form">
                    @csrf
                    <input type="hidden" name="_team_form" value="announcement">
                    @if($editingAnnouncement)
                        @method('PUT')
                    @endif

                    <div class="form-grid-2">
                        <label>Titulo
                            <input name="title" value="{{ old('title', $editingAnnouncement?->title) }}" placeholder="COMUNICADO - Reajuste anual de mensalidades 2026" required>
                        </label>
                        <label>Slug
                            <input name="slug" value="{{ old('slug', $editingAnnouncement?->slug) }}" placeholder="reajuste-anual-mensalidades-2026">
                        </label>
                    </div>

                    <div class="form-grid-2">
                        <label>Categoria
                            <input name="category" value="{{ old('category', $editingAnnouncement?->category ?? 'Comunicado') }}" placeholder="Comunicado" required>
                        </label>
                        <label>Publicado em
                            <input type="date" name="published_at" value="{{ old('published_at', $editingAnnouncement?->published_at?->format('Y-m-d')) }}">
                        </label>
                    </div>

                    <label>Resumo
                        <textarea name="summary" rows="2" required placeholder="Resumo curto que aparece no site e no painel.">{{ old('summary', $editingAnnouncement?->summary) }}</textarea>
                    </label>

                    <label>Conteudo
                        <textarea name="body" rows="4" placeholder="Texto completo do comunicado.">{{ old('body', $editingAnnouncement?->body) }}</textarea>
                    </label>

                    <div class="form-grid-2">
                        <label>Imagem por URL
                            <input type="url" name="image_url" value="{{ old('image_url', $editingAnnouncement?->image_url) }}" placeholder="https://exemplo.com/imagem.jpg">
                        </label>
                        <label class="check-row space-check-row">
                            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $editingAnnouncement?->is_featured ?? false))>
                            Marcar como destaque
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="club-button club-button--blue" type="submit">
                            {{ $editingAnnouncement ? 'Salvar comunicado' : 'Cadastrar comunicado' }}
                        </button>
                        <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#conteudo">Cancelar</a>
                    </div>
                </form>
            </section>
        @endif

        <div class="admin-list">
            @forelse($announcements as $announcement)
                <article class="admin-record-card">
                    <div class="admin-record-card__body">
                        <div class="admin-record-card__head">
                            <div>
                                <strong>{{ $announcement->title }}</strong>
                                <small>{{ $announcement->category }} | {{ $announcement->published_at?->format('d/m/Y') ?? 'rascunho' }}</small>
                            </div>
                            <span class="stock-badge stock-badge--{{ ! $announcement->published_at ? 'muted' : ($announcement->is_featured ? 'warning' : 'success') }}">
                                @if(! $announcement->published_at)
                                    Rascunho
                                @elseif($announcement->is_featured)
                                    Destaque
                                @else
                                    Publicado
                                @endif
                            </span>
                        </div>

                        <p>{{ $announcement->summary }}</p>

                        <div class="admin-record-card__actions">
                            <a class="mini-button mini-button--light" href="{{ route('team.dashboard', ['announcement' => $announcement->id]) }}#conteudo">Editar</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="admin-empty-state">
                    <strong>Nenhum comunicado cadastrado.</strong>
                    <a class="mini-button" href="{{ route('team.dashboard', ['create' => 'announcement']) }}#conteudo">Criar primeiro comunicado</a>
                </div>
            @endforelse
        </div>
    </article>

    <article class="ops-panel">
        <div class="admin-section-head">
            <div>
                <h2>Beneficios</h2>
                <span>{{ $benefits->count() }} registro(s)</span>
            </div>
            <a class="club-button club-button--blue" href="{{ route('team.dashboard', ['create' => 'benefit']) }}#conteudo">Novo beneficio</a>
        </div>

        @if($showBenefitForm)
            <section class="admin-crud-panel">
                <div class="panel-head">
                    <h3>{{ $editingBenefit ? 'Editar beneficio' : 'Novo beneficio' }}</h3>
                    <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#conteudo">Cancelar</a>
                </div>

                <form method="POST" action="{{ $benefitFormAction }}" class="stack-form compact-form">
                    @csrf
                    <input type="hidden" name="_team_form" value="benefit">
                    @if($editingBenefit)
                        @method('PUT')
                    @endif

                    <div class="form-grid-2">
                        <label>Titulo
                            <input name="title" value="{{ old('title', $editingBenefit?->title) }}" placeholder="Convites do mes" required>
                        </label>
                        <label>Categoria
                            <input name="category" value="{{ old('category', $editingBenefit?->category ?? 'Clube') }}" placeholder="Acesso" required>
                        </label>
                    </div>

                    <label>Descricao
                        <textarea name="description" rows="3" required placeholder="O que o associado recebe ou pode usar.">{{ old('description', $editingBenefit?->description) }}</textarea>
                    </label>

                    <div class="form-grid-2">
                        <label>Icone
                            <input name="icon" value="{{ old('icon', $editingBenefit?->icon) }}" placeholder="ticket">
                        </label>
                        <label class="check-row space-check-row">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingBenefit?->is_active ?? true))>
                            Ativo no site e no painel
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="club-button club-button--blue" type="submit">
                            {{ $editingBenefit ? 'Salvar beneficio' : 'Cadastrar beneficio' }}
                        </button>
                        <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#conteudo">Cancelar</a>
                    </div>
                </form>
            </section>
        @endif

        <div class="admin-list">
            @forelse($benefits as $benefit)
                <article class="admin-record-card">
                    <div class="admin-record-card__body">
                        <div class="admin-record-card__head">
                            <div>
                                <strong>{{ $benefit->title }}</strong>
                                <small>{{ $benefit->category }}</small>
                            </div>
                            <span class="stock-badge stock-badge--{{ $benefit->is_active ? 'success' : 'muted' }}">
                                {{ $benefit->is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>

                        <p>{{ $benefit->description }}</p>

                        <div class="admin-record-card__actions">
                            <a class="mini-button mini-button--light" href="{{ route('team.dashboard', ['benefit' => $benefit->id]) }}#conteudo">Editar</a>
                            <form method="POST" action="{{ route('team.benefits.toggle', $benefit) }}">
                                @csrf
                                @method('PATCH')
                                <button class="mini-button {{ $benefit->is_active ? '' : 'mini-button--light' }}" type="submit">
                                    {{ $benefit->is_active ? 'Desativar' : 'Ativar' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="admin-empty-state">
                    <strong>Nenhum beneficio cadastrado.</strong>
                    <a class="mini-button" href="{{ route('team.dashboard', ['create' => 'benefit']) }}#conteudo">Criar primeiro beneficio</a>
                </div>
            @endforelse
        </div>
    </article>
</section>
