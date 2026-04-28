@extends('layouts.club', ['title' => 'AABB Brasília | Clube Digital'])

@section('content')
    <section class="club-hero" style="--hero-image: url('https://aabbdf.com.br/wp-content/uploads/2022/09/complexosaquaticos.jpg')">
        <div class="club-hero__content">
            <p class="overline">Associação Atlética Banco do Brasil</p>
            <h1>AABB Brasília</h1>
            <p class="hero-copy">Um clube vivo, azul e amarelo, agora com associado, reservas, convites e financeiro no mesmo lugar.</p>
            <div class="hero-actions">
                <a href="#adesao" class="club-button club-button--yellow">Quero me associar</a>
                <a href="{{ route('login') }}" class="club-button club-button--light">Acessar portal</a>
            </div>
        </div>
        <div class="club-hero__strip">
            <span>Complexos aquáticos</span>
            <span>Churrasqueiras</span>
            <span>Esportes</span>
            <span>Eventos</span>
            <span>Convites digitais</span>
        </div>
    </section>

    <section class="club-band club-band--yellow">
        <div class="club-band__grid">
            <article>
                <strong>4</strong>
                <span>convites mensais no demo</span>
            </article>
            <article>
                <strong>R$ {{ number_format($plans->max('monthly_family'), 2, ',', '.') }}</strong>
                <span>plano familiar de referência</span>
            </article>
            <article>
                <strong>{{ $spaces->count() }}</strong>
                <span>espaços e áreas no sistema</span>
            </article>
            <article>
                <strong>1</strong>
                <span>portal para resolver tudo</span>
            </article>
        </div>
    </section>

    <section class="club-section" id="estrutura">
        <div class="section-title">
            <p class="overline">Estrutura do clube</p>
            <h2>Quando abre, já dá vontade de estar lá.</h2>
        </div>

        <div class="venue-grid">
            @foreach($spaces as $space)
                <article class="venue-card">
                    <img src="{{ $space->image_url }}" alt="{{ $space->name }}">
                    <div>
                        <span>{{ ucfirst($space->type) }}</span>
                        <h3>{{ $space->name }}</h3>
                        <p>{{ $space->location }} · capacidade para {{ $space->capacity }} pessoas.</p>
                    </div>
                </article>
            @endforeach
            <article class="venue-card">
                <img src="https://aabbdf.com.br/wp-content/uploads/2022/09/Academia-e-crosfit.jpg" alt="Academia e CrossFit">
                <div>
                    <span>Esporte</span>
                    <h3>Academia e CrossFit</h3>
                    <p>Modalidades, escolinhas e rotina esportiva integradas ao clube.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="club-section club-section--blue" id="planos">
        <div class="section-title">
            <p class="overline">Planos e mensalidades</p>
            <h2>Valores de maio/2026 já no sistema.</h2>
        </div>

        <div class="plans-grid">
            @foreach($plans as $plan)
                <article class="plan-card">
                    <h3>{{ $plan->name }}</h3>
                    <p>{{ $plan->segment }}</p>
                    <dl>
                        <div><dt>Familiar</dt><dd>R$ {{ number_format($plan->monthly_family, 2, ',', '.') }}</dd></div>
                        <div><dt>Individual</dt><dd>R$ {{ number_format($plan->monthly_individual, 2, ',', '.') }}</dd></div>
                        <div><dt>Individual 30-</dt><dd>R$ {{ number_format($plan->monthly_under_30, 2, ',', '.') }}</dd></div>
                        @if($plan->monthly_special)
                            <div><dt>Especial</dt><dd>R$ {{ number_format($plan->monthly_special, 2, ',', '.') }}</dd></div>
                        @endif
                    </dl>
                    <span>{{ $plan->included_guests }} convites/mês · {{ $plan->included_dependents }} dependentes inclusos</span>
                </article>
            @endforeach
        </div>
    </section>

    <section class="club-section" id="comunicados">
        <div class="section-title">
            <p class="overline">Comunicados e eventos</p>
            <h2>O associado vê notícia, valor e ação no mesmo ambiente.</h2>
        </div>

        <div class="news-grid">
            @foreach($announcements as $announcement)
                <article class="news-card">
                    <img src="{{ $announcement->image_url }}" alt="{{ $announcement->title }}">
                    <div>
                        <span>{{ $announcement->category }}</span>
                        <h3>{{ $announcement->title }}</h3>
                        <p>{{ $announcement->summary }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="club-section club-section--benefits">
        <div class="section-title">
            <p class="overline">Clube digital</p>
            <h2>O sistema completo aparece como serviço do clube.</h2>
        </div>
        <div class="benefit-grid">
            @foreach($benefits as $benefit)
                <article class="benefit-card">
                    <span>{{ $benefit->category }}</span>
                    <h3>{{ $benefit->title }}</h3>
                    <p>{{ $benefit->description }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="club-section join-section" id="adesao">
        <div>
            <p class="overline">Associe-se</p>
            <h2>Uma proposta entra direto para a secretaria.</h2>
            <p>Este fluxo demonstra a adesão pública do clube. No painel interno, a equipe acompanha propostas, documentos e status.</p>
        </div>
        <form class="join-form" action="{{ route('proposal.store') }}" method="POST">
            @csrf
            <label>Nome completo <input name="name" required placeholder="Seu nome"></label>
            <label>E-mail <input type="email" name="email" required placeholder="voce@email.com"></label>
            <label>Telefone <input name="phone" required placeholder="(61) 99999-9999"></label>
            <label>Plano de interesse
                <select name="plan_id">
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="club-button club-button--blue" type="submit">Enviar interesse</button>
        </form>
    </section>
@endsection
