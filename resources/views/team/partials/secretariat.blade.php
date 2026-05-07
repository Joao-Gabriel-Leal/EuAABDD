@php
    $editingProposal = $proposalEditor;
    $showProposalForm = $editingProposal || request('create') === 'proposal' || old('_team_form') === 'proposal';
    $proposalFormAction = $editingProposal
        ? route('team.proposals.update', $editingProposal)
        : route('team.proposals.store');
    $proposalStatusOptions = [
        'new' => 'Nova',
        'analysis' => 'Em analise',
        'approved' => 'Aprovada',
        'rejected' => 'Reprovada',
    ];
    $proposalSignatureOptions = [
        'pending' => 'Pendente',
        'pending_president_signature' => 'Aguardando presidente',
        'signed' => 'Assinada',
    ];
@endphp

<section class="team-actions team-actions--inside">
    <article class="ops-panel">
        <h2>Importar base</h2>
        <p>CSV/XLSX com colunas: nome, CPF, e-mail, telefone, plano, categoria e dependente.</p>
        <form method="POST" action="{{ route('team.members.import') }}" class="stack-form compact-form" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".csv,.txt,.xlsx" required>
            <button class="club-button club-button--blue" type="submit">Importar associados</button>
        </form>
    </article>

    <article class="ops-panel">
        <h2>Adesoes pendentes</h2>
        <p>Quem entrou pelo site ja virou associado, mas ainda precisa pagar a primeira mensalidade para liberar acesso.</p>
        <div class="ops-row"><span>Associados listados</span><strong>{{ $members->count() }}</strong></div>
        <div class="ops-row"><span>Aguardando pagamento</span><strong>{{ $pendingSignups->count() }}</strong></div>
        <div class="ops-row"><span>Propostas manuais</span><strong>{{ $proposals->count() }}</strong></div>
    </article>
</section>

<section class="ops-grid ops-grid--inside">
    <article class="ops-panel ops-panel-wide">
        <div class="admin-section-head">
            <div>
                <h2>Propostas manuais</h2>
                <span>{{ $proposals->count() }} registro(s) em acompanhamento</span>
            </div>
            <a class="club-button club-button--blue" href="{{ route('team.dashboard', ['create' => 'proposal']) }}#secretaria">Nova proposta manual</a>
        </div>

        @if($showProposalForm)
            <section class="admin-crud-panel">
                <div class="panel-head">
                    <h3>{{ $editingProposal ? 'Editar proposta manual' : 'Nova proposta manual' }}</h3>
                    <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#secretaria">Cancelar</a>
                </div>

                <form method="POST" action="{{ $proposalFormAction }}" class="stack-form compact-form">
                    @csrf
                    <input type="hidden" name="_team_form" value="proposal">
                    @if($editingProposal)
                        @method('PUT')
                    @endif

                    <div class="form-grid-2">
                        <label>Plano
                            <select name="plan_id">
                                <option value="">Plano a definir</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected((string) old('plan_id', $editingProposal?->plan_id) === (string) $plan->id)>
                                        {{ $plan->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>Status
                            <select name="status" required>
                                @foreach($proposalStatusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $editingProposal?->status ?? 'analysis') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="form-grid-2">
                        <label>Nome
                            <input name="name" value="{{ old('name', $editingProposal?->name) }}" placeholder="Familia interessada no plano comunitario" required>
                        </label>
                        <label>CPF
                            <input name="cpf" value="{{ old('cpf', $editingProposal?->cpf) }}" data-mask="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00">
                        </label>
                    </div>

                    <div class="form-grid-2">
                        <label>E-mail
                            <input type="email" name="email" value="{{ old('email', $editingProposal?->email) }}" placeholder="familia@email.com">
                        </label>
                        <label>Telefone
                            <input name="phone" value="{{ old('phone', $editingProposal?->phone) }}" data-mask="phone" inputmode="numeric" maxlength="15" placeholder="(61) 99999-9999">
                        </label>
                    </div>

                    <label>Assinatura
                        <select name="signature_status">
                            @foreach($proposalSignatureOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('signature_status', $editingProposal?->signature_status ?? 'pending') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>Observacoes
                        <textarea name="notes" rows="3" placeholder="Origem, particularidades e combinados da proposta.">{{ old('notes', $editingProposal?->notes) }}</textarea>
                    </label>

                    <div class="form-actions">
                        <button class="club-button club-button--blue" type="submit">
                            {{ $editingProposal ? 'Salvar proposta' : 'Cadastrar proposta' }}
                        </button>
                        <a class="mini-button mini-button--light" href="{{ route('team.dashboard') }}#secretaria">Cancelar</a>
                    </div>
                </form>
            </section>
        @endif

        <div class="admin-list">
            @forelse($proposals as $proposal)
                <article class="admin-record-card">
                    <div class="admin-record-card__body">
                        <div class="admin-record-card__head">
                            <div>
                                <strong>{{ $proposal->name }}</strong>
                                <small>{{ $proposal->email ?: 'sem e-mail' }} | {{ $proposal->plan?->name ?? 'Plano a definir' }} | {{ $proposal->signatureStatusLabel() }}</small>
                            </div>
                            <span class="stock-badge stock-badge--{{ $proposal->status === 'approved' ? 'success' : ($proposal->status === 'rejected' ? 'danger' : 'warning') }}">
                                {{ $proposal->statusLabel() }}
                            </span>
                        </div>

                        <div class="admin-record-card__meta">
                            <span><b>{{ $proposal->cpf ?: 'CPF pendente' }}</b></span>
                            <span><b>{{ $proposal->phone ?: 'Telefone pendente' }}</b></span>
                            <span><b>{{ $proposal->created_at->format('d/m/Y') }}</b> abertura</span>
                        </div>

                        <div class="admin-record-card__actions">
                            <a class="mini-button mini-button--light" href="{{ route('team.dashboard', ['proposal' => $proposal->id]) }}#secretaria">Editar</a>
                            @if($proposal->status !== 'approved')
                                <form method="POST" action="{{ route('team.proposals.approve', $proposal) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="mini-button" type="submit">Aprovar</button>
                                </form>
                            @endif
                            @if($proposal->status === 'approved' && $proposal->signature_status !== 'signed')
                                <form method="POST" action="{{ route('team.proposals.sign', $proposal) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="mini-button" type="submit">Assinar</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="admin-empty-state">
                    <strong>Nenhuma proposta manual cadastrada.</strong>
                    <a class="mini-button" href="{{ route('team.dashboard', ['create' => 'proposal']) }}#secretaria">Criar primeira proposta</a>
                </div>
            @endforelse
        </div>
    </article>

    <article class="ops-panel">
        <h2>Associados</h2>
        @foreach($members as $member)
            <div class="ops-row">
                <span>{{ $member->name }} <small>{{ $member->membership_code }} | {{ $member->statusLabel() }} | dia {{ $member->dueDay() }}</small></span>
                <strong>{{ $member->plan->name }}</strong>
            </div>
        @endforeach
    </article>

    <article class="ops-panel">
        <h2>Dependentes</h2>
        @foreach($dependents as $dependent)
            <div class="ops-row">
                <span>{{ $dependent->name }} <small>{{ $dependent->member->name }} | {{ $dependent->relationship }}</small></span>
                <strong>{{ $dependent->is_free ? 'Cortesia' : 'Taxado' }}</strong>
            </div>
        @endforeach
    </article>

    <article class="ops-panel ops-panel-wide">
        <h2>Adesoes aguardando pagamento</h2>
        @forelse($pendingSignups as $member)
            @php($invoice = $member->invoices->firstWhere('type', 'membership_initial'))
            <div class="ops-row">
                <span>{{ $member->name }} <small>{{ $member->email }} | {{ $member->plan?->name }} {{ $member->category }} | {{ $member->membership_code }}</small></span>
                <strong>R$ {{ number_format((float) ($invoice?->amount ?? $member->monthlyAmount()), 2, ',', '.') }}</strong>
            </div>
        @empty
            <p>Nenhuma adesao direta pendente agora.</p>
        @endforelse
    </article>
</section>
