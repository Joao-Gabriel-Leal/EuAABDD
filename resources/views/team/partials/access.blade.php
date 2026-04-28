<section class="team-actions team-actions--inside">
    <article class="ops-panel">
        <h2>Validar convite</h2>
        <p>Registra acesso pela portaria e bloqueia convite invalido, usado, vencido ou associado inadimplente.</p>
        <form method="POST" action="{{ route('team.access.register') }}" class="inline-form" data-access-validation-form>
            @csrf
            <input name="code" placeholder="Codigo AABB-..." required autocomplete="off">
            <input name="gate" placeholder="Portaria principal">
            <button class="mini-button" type="submit">Validar</button>
        </form>
        <div class="access-result" data-access-result hidden></div>
    </article>

    <article class="ops-panel">
        <h2>Validar carteirinha</h2>
        <p>Cole o token lido pelo QR Code ou escaneie com a camera da portaria para abrir a validacao interna.</p>
        <form class="inline-form" data-card-validation-form>
            <input name="token" placeholder="Token da carteirinha" required>
            <input name="gate" placeholder="Portaria principal" aria-label="Portaria">
            <button class="mini-button" type="submit">Abrir validacao</button>
        </form>
    </article>
</section>

<section class="ops-grid ops-grid--inside">
    <article class="ops-panel ops-panel-wide">
        <h2>Ultimos acessos</h2>
        <div data-access-log-list>
            @foreach($accessLogs as $log)
                <div class="ops-row">
                    <span>{{ $log->person_name }} <small>{{ $log->person_type }} | {{ $log->gate }} | {{ $log->checked_at->format('d/m/Y H:i') }}</small></span>
                    <strong class="{{ $log->status === 'allowed' ? 'ok' : 'warn' }}">{{ $log->status === 'allowed' ? 'Liberado' : 'Bloqueado' }}</strong>
                </div>
            @endforeach
        </div>
    </article>
</section>
