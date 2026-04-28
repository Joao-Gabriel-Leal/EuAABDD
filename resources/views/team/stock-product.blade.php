@extends('layouts.club', ['title' => 'Ficha de Estoque | AABB Brasilia'])

@section('content')
    <section class="stock-product-hero">
        <div>
            <p class="overline">Ficha interna de estoque</p>
            <h1>{{ $product->name }}</h1>
            <p>{{ $product->sku }} | {{ $product->category }} | leitura via QR Code protegido da equipe.</p>
        </div>
        <a class="club-button club-button--yellow" href="{{ route('team.dashboard') }}#estoque">Voltar ao estoque</a>
    </section>

    <section class="stock-product-page">
        <article class="ops-panel stock-product-summary stock-product-card--{{ $product->stockStatusTone() }}">
            <span class="stock-badge stock-badge--{{ $product->stockStatusTone() }}">{{ $product->stockStatusLabel() }}</span>
            <h2>Resumo do item</h2>
            <div class="stock-product-card__numbers">
                <span><b>{{ $product->quantity }}</b> {{ $product->unit }} em saldo</span>
                <span><b>R$ {{ number_format((float) $product->unit_cost, 2, ',', '.') }}</b> custo unitario</span>
                <span><b>R$ {{ number_format($product->stockValue(), 2, ',', '.') }}</b> valor total</span>
            </div>
            <p>{{ $product->description ?? 'Sem descricao operacional cadastrada.' }}</p>
            <dl class="stock-detail-list">
                <div><dt>Localizacao</dt><dd>{{ $product->location ?? 'Nao informada' }}</dd></div>
                <div><dt>Fornecedor</dt><dd>{{ $product->supplier ?? 'Nao informado' }}</dd></div>
                <div><dt>Estoque minimo</dt><dd>{{ $product->minimum_quantity }} {{ $product->unit }}</dd></div>
                <div><dt>Status</dt><dd>{{ $product->is_active ? 'Ativo' : 'Inativo' }}</dd></div>
            </dl>
        </article>

        <article class="ops-panel stock-product-qr-panel">
            <h2>QR Code do produto</h2>
            <img src="{{ $stockQrCode }}" alt="QR Code do produto {{ $product->name }}">
            <strong>{{ $product->sku }}</strong>
            <p>Esse QR abre a ficha interna apenas para usuarios da equipe autenticados.</p>
            <button class="mini-button" type="button" onclick="window.print()">Imprimir QR</button>
        </article>

        <article class="ops-panel ops-panel-wide">
            <div class="panel-head">
                <h2>Historico de movimentacoes</h2>
                <span>{{ $latestMovements->count() }} registro(s)</span>
            </div>

            <div class="movement-log">
                @foreach($latestMovements as $movement)
                    <div class="movement-log__row">
                        <span class="stock-badge stock-badge--{{ $movement->typeTone() }}">{{ $movement->typeLabel() }}</span>
                        <div>
                            <strong>{{ $movement->movement_code }}</strong>
                            <small>
                                {{ $movement->quantity_before }} -> {{ $movement->quantity_after }}
                                | {{ $movement->createdBy?->name ?? 'Sistema' }}
                                | {{ $movement->created_at->format('d/m/Y H:i') }}
                                @if($movement->reason)
                                    | {{ $movement->reason }}
                                @endif
                            </small>
                        </div>
                        <strong>R$ {{ number_format((float) $movement->total_cost, 2, ',', '.') }}</strong>
                    </div>
                @endforeach
            </div>
        </article>
    </section>
@endsection
