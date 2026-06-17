@extends('layouts.club', ['title' => 'Equipe | AABB Brasilia'])

@section('content')
    @php
        $teamTabs = collect([
            ['id' => 'reservas', 'label' => 'Reservas', 'area' => 'Clube', 'title' => 'Churrasqueiras, agenda e convidados', 'view' => 'team.partials.reservations'],
            ['id' => 'importacao', 'label' => 'Importar usuarios', 'area' => 'Base', 'title' => 'Carga inicial de associados', 'view' => 'team.partials.member-import'],
            ['id' => 'pagamentos', 'label' => 'Pagamentos AABB', 'area' => 'Recebimento', 'title' => 'Comprovantes, baixa e gateway', 'view' => 'team.partials.payments'],
        ]);
    @endphp

    <section class="team-header">
        <div>
            <p class="overline">Painel da equipe</p>
            <h1>Modulo de reservas AABB</h1>
            <p>Agenda de churrasqueiras, convidados, importacao da base e pagamentos AABB em uma operacao inicial enxuta.</p>
        </div>
    </section>

    <section class="team-workspace" data-team-tabs data-default-tab="reservas">
        <div class="team-tab-nav" role="tablist" aria-label="Modulo de reservas AABB">
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
