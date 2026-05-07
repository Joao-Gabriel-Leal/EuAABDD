Ola, {{ $invitation->guest?->name }}.

Voce foi incluido na lista de convidados da reserva {{ $invitation->guest?->reservation?->space?->name }} na AABB Brasilia.

Data da reserva: {{ $invitation->valid_for->format('d/m/Y') }}
Horario: {{ $invitation->guest?->reservation?->starts_at }} as {{ $invitation->guest?->reservation?->ends_at }}
Valor do rateio: R$ {{ number_format((float) $invitation->amount, 2, ',', '.') }}
Codigo do convite: {{ $invitation->code }}
Cobranca: {{ $invitation->invoice?->number }}

O convite sera liberado para a portaria depois da baixa do pagamento.
Apresente o codigo acima na entrada do clube quando o convite estiver liberado.
