@php
    $activeProducts = $products->where('is_active', true);
    $recentMovementsCount = $stockMovements->count();
@endphp

<section class="stock-dashboard">
    <div class="stock-kpis">
        <article>
            <span>Valor em estoque</span>
            <strong>R$ {{ number_format($stockTotalValue, 2, ',', '.') }}</strong>
            <small>Custo unitario x saldo atual</small>
        </article>
        <article>
            <span>Abaixo do minimo</span>
            <strong>{{ $lowStockCount }}</strong>
            <small>Itens pedindo compra</small>
        </article>
        <article>
            <span>Zerados</span>
            <strong>{{ $zeroStockCount }}</strong>
            <small>Risco operacional imediato</small>
        </article>
        <article>
            <span>Movimentos recentes</span>
            <strong>{{ $recentMovementsCount }}</strong>
            <small>Ultimas auditorias registradas</small>
        </article>
    </div>

    <div class="stock-layout">
        <article class="ops-panel stock-alert-panel">
            <div class="panel-head">
                <h2>Alertas de reposicao</h2>
                <span>{{ $stockAlerts->count() }} alerta(s)</span>
            </div>

            @forelse($stockAlerts as $product)
                <div class="stock-alert stock-alert--{{ $product->stockStatusTone() }}">
                    <strong>{{ $product->name }}</strong>
                    <span>{{ $product->quantity }} {{ $product->unit }} em estoque | minimo {{ $product->minimum_quantity }}</span>
                    <small>{{ $product->location ?? 'Sem local informado' }}</small>
                </div>
            @empty
                <div class="stock-empty-state">
                    <strong>Estoque saudavel</strong>
                    <span>Nenhum item abaixo do minimo neste momento.</span>
                </div>
            @endforelse
        </article>

        <article class="ops-panel stock-movement-panel">
            <h2>Registrar movimento</h2>
            <p>Entrada, saida, perda ou ajuste ficam com saldo anterior, saldo final, custo e usuario responsavel.</p>

            @if($activeProducts->isNotEmpty())
                <form method="POST" action="{{ route('team.stock.move', $activeProducts->first()) }}" class="stack-form stock-movement-form" data-stock-movement-form>
                    @csrf
                    <label>Produto
                        <select name="product_route" data-stock-product-route>
                            @foreach($activeProducts as $product)
                                <option value="{{ route('team.stock.move', $product) }}">
                                    {{ $product->sku }} | {{ $product->name }} | saldo {{ $product->quantity }} {{ $product->unit }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <div class="form-grid-2">
                        <label>Tipo
                            <select name="type" data-stock-movement-type>
                                <option value="entry">Entrada</option>
                                <option value="exit">Saida</option>
                                <option value="adjustment">Ajuste de saldo final</option>
                                <option value="loss">Perda / avaria</option>
                            </select>
                        </label>
                        <label>Quantidade
                            <input name="quantity" type="number" min="0" value="1" required>
                        </label>
                    </div>

                    <div class="form-grid-2">
                        <label>Custo unitario
                            <input name="unit_cost" type="number" min="0" step="0.01" placeholder="0,00">
                        </label>
                        <label>Motivo
                            <input name="reason" placeholder="Compra, consumo, inventario, avaria...">
                        </label>
                    </div>

                    <button class="club-button club-button--blue" type="submit">Registrar no estoque</button>
                </form>
            @else
                <p>Nenhum produto ativo cadastrado.</p>
            @endif
        </article>
    </div>

    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Produtos e QR Codes</h2>
            <span>{{ $products->count() }} item(ns) controlado(s)</span>
        </div>

        <div class="stock-product-grid">
            @foreach($products as $product)
                <section class="stock-product-card stock-product-card--{{ $product->stockStatusTone() }}">
                    <div class="stock-product-card__main">
                        <span class="stock-badge stock-badge--{{ $product->stockStatusTone() }}">{{ $product->stockStatusLabel() }}</span>
                        <strong>{{ $product->name }}</strong>
                        <small>{{ $product->sku }} | {{ $product->category }} | {{ $product->location ?? 'Sem local' }}</small>

                        <div class="stock-product-card__numbers">
                            <span><b>{{ $product->quantity }}</b> {{ $product->unit }}</span>
                            <span><b>R$ {{ number_format((float) $product->unit_cost, 2, ',', '.') }}</b> custo</span>
                            <span><b>R$ {{ number_format($product->stockValue(), 2, ',', '.') }}</b> total</span>
                        </div>

                        <p>{{ $product->description ?? 'Produto sem descricao operacional.' }}</p>
                    </div>

                    <div class="stock-product-card__qr">
                        @if($stockQrCodes[$product->id] ?? null)
                            <img src="{{ $stockQrCodes[$product->id] }}" alt="QR Code do produto {{ $product->name }}">
                        @endif
                        <a class="mini-button" href="{{ route('team.stock.product.show', $product->qr_token) }}">Abrir ficha</a>
                    </div>
                </section>
            @endforeach
        </div>
    </article>

    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Log de movimentacoes</h2>
            <span>Auditoria de saldo e custo</span>
        </div>

        <div class="movement-log">
            @foreach($stockMovements as $movement)
                <div class="movement-log__row">
                    <span class="stock-badge stock-badge--{{ $movement->typeTone() }}">{{ $movement->typeLabel() }}</span>
                    <div>
                        <strong>{{ $movement->product?->name ?? 'Produto removido' }}</strong>
                        <small>
                            {{ $movement->movement_code }}
                            | {{ $movement->quantity_before }} -> {{ $movement->quantity_after }}
                            | {{ $movement->createdBy?->name ?? 'Sistema' }}
                            | {{ $movement->created_at->format('d/m/Y H:i') }}
                        </small>
                    </div>
                    <strong>R$ {{ number_format((float) $movement->total_cost, 2, ',', '.') }}</strong>
                </div>
            @endforeach
        </div>
    </article>
</section>
