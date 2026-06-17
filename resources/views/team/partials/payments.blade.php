<section class="team-actions team-actions--inside">
    <article class="ops-panel">
        <h2>Pagamentos AABB</h2>
        <p>Fluxo atual manual preparado para gateway: QR App AABB, boleto, debito em conta e cartao presencial.</p>
        <div class="ops-row"><span>Cobrancas abertas</span><strong>{{ $openInvoicesCount }}</strong></div>
        <div class="ops-row"><span>Cobrancas vencidas</span><strong>{{ $overdueInvoicesCount }}</strong></div>
    </article>

    <article class="ops-panel">
        <h2>Gateway</h2>
        <div class="ops-row"><span>Driver ativo</span><strong>{{ config('aabb_payments.driver', 'manual') }}</strong></div>
        <div class="ops-row"><span>Modo</span><strong>Baixa manual</strong></div>
    </article>
</section>

<section class="ops-grid ops-grid--inside">
    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Cobrancas de reservas</h2>
            <span>{{ $invoices->count() }} registro(s)</span>
        </div>

        @forelse($invoices as $invoice)
            <div class="ops-row finance-row">
                <span>
                    {{ $invoice->member?->name ?? 'Associado removido' }}
                    <small>{{ $invoice->number }} | {{ $invoice->description }} | {{ $invoice->statusLabel() }}</small>
                </span>
                <strong class="{{ $invoice->status === 'paid' ? 'ok' : 'warn' }}">R$ {{ number_format($invoice->amount, 2, ',', '.') }}</strong>
                @if(auth()->user()->canManageFinance() && $invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                    <form method="POST" action="{{ route('team.invoices.pay', $invoice) }}" class="inline-form pay-inline" enctype="multipart/form-data">
                        @csrf
                        <input name="amount" type="number" step="0.01" value="{{ $invoice->amount }}" required>
                        <select name="method" required>
                            @foreach(config('aabb_payments.allowed_methods', []) as $method)
                                <option value="{{ $method }}">{{ $method }}</option>
                            @endforeach
                        </select>
                        <input name="paid_at" type="date" value="{{ now()->format('Y-m-d') }}" required>
                        <input name="manual_reference" placeholder="Referencia opcional">
                        <button class="mini-button" type="submit">Baixar</button>
                    </form>
                @endif
            </div>
        @empty
            <p>Nenhuma cobranca do modulo de reservas encontrada.</p>
        @endforelse
    </article>

    <article class="ops-panel">
        <h2>Pagamentos recentes</h2>
        @forelse($payments as $payment)
            <div class="ops-row">
                <span>{{ $payment->invoice?->member?->name ?? 'Associado removido' }} <small>{{ $payment->method }} | {{ $payment->paid_at?->format('d/m/Y') ?? 'sem data' }}</small></span>
                <strong>R$ {{ number_format($payment->amount, 2, ',', '.') }}</strong>
            </div>
        @empty
            <p>Nenhum pagamento confirmado ainda.</p>
        @endforelse
    </article>
</section>
