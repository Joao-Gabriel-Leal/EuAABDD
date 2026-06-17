<section class="team-actions team-actions--inside">
    <article class="ops-panel">
        <h2>Importar base de associados</h2>
        <p>CSV/XLSX com colunas: nome, CPF, e-mail, telefone, plano, categoria, senha e dependente.</p>
        <form method="POST" action="{{ route('team.members.import') }}" class="stack-form compact-form" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".csv,.txt,.xlsx" required>
            <button class="club-button club-button--blue" type="submit">Importar usuarios</button>
        </form>
    </article>

    <article class="ops-panel">
        <h2>Base atual</h2>
        <div class="ops-row"><span>Associados ativos</span><strong>{{ $membersCount }}</strong></div>
        <div class="ops-row"><span>Ultimos associados listados</span><strong>{{ $members->count() }}</strong></div>
    </article>
</section>

<section class="ops-grid ops-grid--inside">
    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Ultimas importacoes</h2>
            <span>{{ $importBatches->count() }} lote(s)</span>
        </div>

        @forelse($importBatches as $batch)
            <div class="ops-row">
                <span>
                    {{ $batch->filename }}
                    <small>{{ $batch->status }} | {{ $batch->success_rows }} sucesso(s), {{ $batch->failed_rows }} erro(s)</small>
                </span>
                <strong>{{ $batch->finished_at?->format('d/m/Y H:i') ?? $batch->created_at->format('d/m/Y H:i') }}</strong>
            </div>
        @empty
            <p>Nenhuma importacao realizada ainda.</p>
        @endforelse
    </article>

    <article class="ops-panel">
        <h2>Associados recentes</h2>
        @forelse($members as $member)
            <div class="ops-row">
                <span>{{ $member->name }} <small>{{ $member->membership_code }} | {{ $member->email ?: 'sem e-mail' }}</small></span>
                <strong>{{ $member->plan?->name ?? 'Plano pendente' }}</strong>
            </div>
        @empty
            <p>Nenhum associado encontrado.</p>
        @endforelse
    </article>
</section>
