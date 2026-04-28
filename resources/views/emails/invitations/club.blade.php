Ola, {{ $invitation->guest?->name }}.

Voce recebeu um convite para acessar a AABB Brasilia.

Codigo do convite: {{ $invitation->code }}
Valido em: {{ $invitation->valid_for->format('d/m/Y') }}

Apresente este codigo na portaria do clube.

{{ $invitation->shareText() }}
