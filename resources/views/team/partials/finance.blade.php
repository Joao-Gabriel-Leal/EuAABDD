<section class="team-actions team-actions--inside">
    <article class="ops-panel">
        <h2>Gerar mensalidades</h2>
        <p>Cria cobrancas recorrentes por associado ativo, respeitando vencimento do plano/associado e ignorando duplicadas.</p>
        <form method="POST" action="{{ route('team.billing.monthly') }}" class="inline-form">
            @csrf
            <input name="month" type="number" min="1" max="12" value="{{ now()->month }}" required>
            <input name="year" type="number" min="2024" max="2035" value="{{ now()->year }}" required>
            <button class="mini-button" type="submit">Gerar</button>
        </form>
    </article>

    <article class="ops-panel">
        <h2>Fluxo de caixa</h2>
        <div class="ops-row"><span>Entradas</span><strong class="ok">R$ {{ number_format($income, 2, ',', '.') }}</strong></div>
        <div class="ops-row"><span>Saidas</span><strong class="warn">R$ {{ number_format($expenses, 2, ',', '.') }}</strong></div>
        <div class="ops-row"><span>Saldo demonstrativo</span><strong>R$ {{ number_format($income - $expenses, 2, ',', '.') }}</strong></div>
    </article>
</section>

<section class="ops-grid ops-grid--inside">
    <article class="ops-panel ops-panel-wide">
        <div class="panel-head">
            <h2>Primeiras mensalidades da adesao</h2>
            <span>{{ $initialSignupInvoices->count() }} aberta(s)</span>
        </div>
        @forelse($initialSignupInvoices as $invoice)
            <div class="ops-row finance-row finance-row--highlight">
                <span>
                    {{ $invoice->member->name }}
                    <small>{{ $invoice->member->membership_code }} | {{ $invoice->description }} | {{ $invoice->statusLabel() }}</small>
                </span>
                <strong class="warn">R$ {{ number_format($invoice->amount, 2, ',', '.') }}</strong>
                @if(auth()->user()->canManageFinance())
                    <form method="POST" action="{{ route('team.invoices.pay', $invoice) }}" class="inline-form pay-inline" enctype="multipart/form-data">
                        @csrf
                        <input name="amount" type="number" step="0.01" value="{{ $invoice->amount }}" required>
                        <select name="method" required>
                            <option value="QR App AABB">QR App AABB</option>
                            <option value="Boleto Banco do Brasil">Boleto Banco do Brasil</option>
                            <option value="Debito em conta Banco do Brasil">Debito em conta Banco do Brasil</option>
                        </select>
                        <input name="paid_at" type="date" value="{{ now()->format('Y-m-d') }}" required>
                        <button class="mini-button" type="submit">Ativar</button>
                    </form>
                @endif
            </div>
        @empty
            <p>Nenhuma adesao aguardando pagamento inicial.</p>
        @endforelse
    </article>

    <article class="ops-panel ops-panel-wide">
        <h2>Cobrancas</h2>
        @foreach($invoices as $invoice)
            <div class="ops-row finance-row">
                <span>
                    {{ $invoice->member->name }}
                    <small>{{ $invoice->description }} | {{ $invoice->statusLabel() }} | vence {{ $invoice->due_date->format('d/m/Y') }}</small>
                </span>
                <strong class="{{ $invoice->status === 'paid' ? 'ok' : 'warn' }}">R$ {{ number_format($invoice->amount, 2, ',', '.') }}</strong>
                @if(auth()->user()->canManageFinance() && $invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                    <form method="POST" action="{{ route('team.invoices.pay', $invoice) }}" class="inline-form pay-inline" enctype="multipart/form-data">
                        @csrf
                        <input name="amount" type="number" step="0.01" value="{{ $invoice->amount }}" required>
                        <select name="method" required>
                            <option value="Boleto Banco do Brasil">Boleto Banco do Brasil</option>
                            <option value="Debito em conta Banco do Brasil">Debito em conta Banco do Brasil</option>
                            <option value="QR App AABB">QR App AABB</option>
                            <option value="Cartao presencial">Cartao presencial</option>
                        </select>
                        <input name="paid_at" type="date" value="{{ now()->format('Y-m-d') }}" required>
                        <button class="mini-button" type="submit">Baixar</button>
                    </form>
                @endif
            </div>
        @endforeach
    </article>

    <article class="ops-panel">
        <h2>Pagamentos</h2>
        @foreach($payments as $payment)
            <div class="ops-row">
                <span>{{ $payment->invoice->member->name }} <small>{{ $payment->method }} | {{ $payment->paid_at?->format('d/m/Y') ?? 'sem data' }}</small></span>
                <strong>R$ {{ number_format($payment->amount, 2, ',', '.') }}</strong>
            </div>
        @endforeach
    </article>

    <article class="ops-panel">
        <h2>Lancamentos</h2>
        @foreach($cashEntries as $entry)
            <div class="ops-row">
                <span>{{ $entry->description }} <small>{{ $entry->category }} | {{ $entry->entry_date->format('d/m/Y') }}</small></span>
                <strong class="{{ $entry->type === 'income' ? 'ok' : 'warn' }}">R$ {{ number_format($entry->amount, 2, ',', '.') }}</strong>
            </div>
        @endforeach
    </article>
</section>
