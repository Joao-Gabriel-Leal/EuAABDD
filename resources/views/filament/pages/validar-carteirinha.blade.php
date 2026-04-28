<x-filament-panels::page>
    <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-wide text-amber-500">Portaria AABB</p>
        <h2 class="mt-2 text-2xl font-black text-blue-900">Validação interna por QR Code</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">
            Ao escanear a carteirinha do associado, a portaria abre uma página protegida com status,
            plano, dependentes ativos, pendências financeiras e permissão de acesso.
        </p>

        <form class="mt-6 flex flex-col gap-3 sm:flex-row" onsubmit="event.preventDefault(); const token = this.token.value.trim(); if (token) window.location.href = '/carteirinha/validar/' + encodeURIComponent(token);">
            <input
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                name="token"
                placeholder="Cole aqui o token completo da carteirinha"
                required
            >
            <button class="rounded-xl bg-blue-900 px-5 py-3 text-sm font-black text-white" type="submit">
                Validar carteirinha
            </button>
        </form>

        <a class="mt-5 inline-flex rounded-xl bg-amber-300 px-4 py-2 text-sm font-black text-blue-950" href="{{ route('team.dashboard') }}">
            Voltar para operação da equipe
        </a>
    </div>
</x-filament-panels::page>
