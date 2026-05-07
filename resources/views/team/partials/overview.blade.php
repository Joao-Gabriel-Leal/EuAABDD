<form method="GET" action="{{ route('team.dashboard') }}" class="team-metrics-filter">
    <div class="team-metrics-filter__fields">
        <label>
            <span>De</span>
            <input name="metrics_from" type="date" value="{{ $metricsFrom->toDateString() }}" required>
        </label>
        <label>
            <span>Até</span>
            <input name="metrics_to" type="date" value="{{ $metricsTo->toDateString() }}" required>
        </label>
    </div>
    <button class="mini-button" type="submit">Aplicar</button>
</form>

<section class="team-metrics team-metrics--inside">
    <article><span>Associados ativos</span><strong>{{ $membersCount }}</strong><small>Situação atual</small></article>
    <article><span>Recebido no período</span><strong>R$ {{ number_format($paidAmount, 2, ',', '.') }}</strong><small>{{ $metricsPeriodLabel }}</small></article>
    <article><span>Aberto/análise no período</span><strong>R$ {{ number_format($pendingAmount, 2, ',', '.') }}</strong><small>{{ $metricsPeriodLabel }}</small></article>
    <article><span>Vencido no período</span><strong>R$ {{ number_format($overdueAmount, 2, ',', '.') }}</strong><small>{{ $metricsPeriodLabel }}</small></article>
    <article><span>Estoque baixo</span><strong>{{ $lowStockCount }}</strong><small>Situação atual</small></article>
</section>

<section class="ops-grid ops-grid--inside">
    @if(auth()->user()->canManageSecretariat())
        <button class="module-jump" type="button" data-team-tab-jump="secretaria">
            <span>Secretaria</span>
            <strong>{{ $proposals->count() }} propostas recentes</strong>
            <small>Associados, dependentes, importação e cadastro.</small>
        </button>
    @endif

    @if(auth()->user()->canManageFinance())
        <button class="module-jump" type="button" data-team-tab-jump="financeiro">
            <span>Financeiro</span>
            <strong>{{ $openInvoicesCount }} cobranças abertas</strong>
            <small>Mensalidades, baixas, pagamentos e caixa.</small>
        </button>
    @endif

    <button class="module-jump" type="button" data-team-tab-jump="reservas">
        <span>Reservas</span>
        <strong>{{ $scheduledReservationsCount }} próximas reservas</strong>
        <small>Churrasqueiras, convidados e convites excedentes.</small>
    </button>

    @if(auth()->user()->canManageAccess())
        <button class="module-jump" type="button" data-team-tab-jump="portaria">
            <span>Portaria</span>
            <strong>{{ $todayAccessCount }} acessos hoje</strong>
            <small>Valide QR, convites e registre entradas.</small>
        </button>
    @endif

    <button class="module-jump" type="button" data-team-tab-jump="estoque">
        <span>Estoque</span>
        <strong>{{ $lowStockCount }} item(ns) em alerta</strong>
        <small>Controle entradas, saídas e mínimos.</small>
    </button>

    @if(auth()->user()->canManageSecretariat())
        <button class="module-jump" type="button" data-team-tab-jump="conteudo">
            <span>Conteúdo</span>
            <strong>{{ $announcements->count() }} comunicados recentes</strong>
            <small>Comunicados e benefícios do clube.</small>
        </button>
    @endif
</section>
