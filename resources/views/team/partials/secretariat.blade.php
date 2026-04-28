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

    <article class="ops-panel ops-panel-wide">
        <h2>Propostas manuais</h2>
        @foreach($proposals as $proposal)
            <div class="ops-row">
                <span>{{ $proposal->name }} <small>{{ $proposal->email }} | {{ $proposal->plan?->name ?? 'Plano a definir' }}</small></span>
                <strong>{{ $proposal->statusLabel() }}</strong>
            </div>
        @endforeach
    </article>
</section>
