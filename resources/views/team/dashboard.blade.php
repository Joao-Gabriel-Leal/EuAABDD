@extends('layouts.club', ['title' => 'Equipe | AABB Brasília'])

@section('content')
    @php
        $user = auth()->user();
        $teamTabs = collect([
            ['id' => 'visao-geral', 'label' => 'Visão geral', 'area' => 'Painel', 'title' => 'Resumo operacional', 'view' => 'team.partials.overview', 'allowed' => true],
            ['id' => 'secretaria', 'label' => 'Secretaria', 'area' => 'Base social', 'title' => 'Associados, dependentes e propostas', 'view' => 'team.partials.secretariat', 'allowed' => $user->canManageSecretariat()],
            ['id' => 'financeiro', 'label' => 'Financeiro', 'area' => 'Receita', 'title' => 'Cobranças, pagamentos e caixa', 'view' => 'team.partials.finance', 'allowed' => $user->canManageFinance()],
            ['id' => 'reservas', 'label' => 'Reservas e Convites', 'area' => 'Clube', 'title' => 'Churrasqueiras, convidados e cotas', 'view' => 'team.partials.reservations', 'allowed' => $user->hasInternalRole()],
            ['id' => 'portaria', 'label' => 'Portaria', 'area' => 'Acesso', 'title' => 'Carteirinhas, convites e entradas', 'view' => 'team.partials.access', 'allowed' => $user->canManageAccess()],
            ['id' => 'estoque', 'label' => 'Estoque', 'area' => 'Operação', 'title' => 'Produtos e movimentações', 'view' => 'team.partials.stock', 'allowed' => $user->hasInternalRole()],
            ['id' => 'conteudo', 'label' => 'Conteúdo', 'area' => 'Comunicação', 'title' => 'Comunicados e benefícios', 'view' => 'team.partials.content', 'allowed' => $user->canManageSecretariat()],
        ])->filter(fn ($tab) => $tab['allowed'])->values();
    @endphp

    <section class="team-header">
        <div>
            <p class="overline">Painel da equipe</p>
            <h1>Operação AABB Brasília</h1>
            <p>Tudo que o funcionário precisa em uma única tela: secretaria, financeiro, reservas, portaria, estoque e comunicação.</p>
        </div>
    </section>

    <section class="team-workspace" data-team-tabs>
        <div class="team-tab-nav" role="tablist" aria-label="Módulos da equipe AABB">
            @foreach($teamTabs as $index => $tab)
                <button
                    type="button"
                    role="tab"
                    class="team-tab-card {{ $index === 0 ? 'is-active' : '' }}"
                    id="team-tab-{{ $tab['id'] }}"
                    aria-controls="team-panel-{{ $tab['id'] }}"
                    aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                    tabindex="{{ $index === 0 ? '0' : '-1' }}"
                    data-team-tab-target="{{ $tab['id'] }}"
                >
                    <span>{{ $tab['area'] }}</span>
                    <strong>{{ $tab['label'] }}</strong>
                    <small>{{ $tab['title'] }}</small>
                </button>
            @endforeach
        </div>

        @foreach($teamTabs as $index => $tab)
            <section
                class="team-tab-panel"
                id="team-panel-{{ $tab['id'] }}"
                role="tabpanel"
                aria-labelledby="team-tab-{{ $tab['id'] }}"
                data-team-tab-panel="{{ $tab['id'] }}"
                @if($index !== 0) hidden @endif
            >
                @include($tab['view'])
            </section>
        @endforeach
    </section>
@endsection
