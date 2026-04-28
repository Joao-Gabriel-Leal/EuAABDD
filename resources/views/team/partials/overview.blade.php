<section class="team-metrics team-metrics--inside">
    <article><span>Associados ativos</span><strong>{{ $membersCount }}</strong></article>
    <article><span>Recebido</span><strong>R$ {{ number_format($paidAmount, 2, ',', '.') }}</strong></article>
    <article><span>Aberto/análise</span><strong>R$ {{ number_format($pendingAmount, 2, ',', '.') }}</strong></article>
    <article><span>Vencido</span><strong>R$ {{ number_format($overdueAmount, 2, ',', '.') }}</strong></article>
    <article><span>Estoque baixo</span><strong>{{ $lowStockCount }}</strong></article>
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
