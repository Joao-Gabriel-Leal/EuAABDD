<section class="ops-grid ops-grid--inside">
    <article class="ops-panel">
        <h2>Comunicados</h2>
        @foreach($announcements as $announcement)
            <div class="ops-row">
                <span>{{ $announcement->title }} <small>{{ $announcement->category }} · {{ $announcement->published_at?->format('d/m/Y') ?? 'rascunho' }}</small></span>
                <strong>{{ $announcement->is_featured ? 'Destaque' : 'Publicado' }}</strong>
            </div>
        @endforeach
    </article>

    <article class="ops-panel">
        <h2>Benefícios</h2>
        @foreach($benefits as $benefit)
            <div class="ops-row">
                <span>{{ $benefit->title }} <small>{{ $benefit->category }}</small></span>
                <strong>{{ $benefit->is_active ? 'Ativo' : 'Inativo' }}</strong>
            </div>
        @endforeach
    </article>
</section>
