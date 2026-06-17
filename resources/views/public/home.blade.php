@extends('layouts.club', ['title' => 'Reservas AABB Brasilia'])

@section('content')
    <section class="club-hero" style="--hero-image: url('{{ asset('images/aabb.jpg') }}')">
        <div class="club-hero__content">
            <p class="overline">Modulo de reservas</p>
            <h1>Reservas AABB Brasilia</h1>
            <p class="hero-copy">Acesse sua conta para reservar churrasqueiras, acompanhar pagamentos e gerenciar convidados da reserva.</p>
            <div class="hero-actions">
                <a href="{{ route('login') }}" class="club-button club-button--yellow">Entrar no sistema</a>
            </div>
        </div>
        <div class="club-hero__strip">
            <span>Agenda</span>
            <span>Churrasqueiras</span>
            <span>Convidados</span>
            <span>Pagamentos AABB</span>
        </div>
    </section>

    <section class="club-band club-band--yellow">
        <div class="club-band__grid">
            <article>
                <strong>{{ $spaces->count() }}</strong>
                <span>espaco(s) reservavel(is)</span>
            </article>
            <article>
                <strong>{{ $spaces->where('type', 'churrasqueira')->count() }}</strong>
                <span>churrasqueira(s) no mapa</span>
            </article>
            <article>
                <strong>1</strong>
                <span>portal para reservas e pagamentos</span>
            </article>
        </div>
    </section>

    <section class="club-section" id="reservas">
        <div class="section-title">
            <p class="overline">Estrutura para reservar</p>
            <h2>Escolha pelo portal, confirme o pagamento e envie a lista de convidados.</h2>
        </div>

        <div class="venue-grid">
            @foreach($spaces as $space)
                <article class="venue-card">
                    <img src="{{ $space->image_url }}" alt="{{ $space->name }}">
                    <div>
                        <span>{{ $space->typeName() }}</span>
                        <h3>{{ $space->name }}</h3>
                        <p>{{ $space->location }} | capacidade para {{ $space->capacity }} pessoas.</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
