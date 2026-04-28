@php
    $messages = [];

    foreach ([
        'team_status' => session('team_status_type', 'success'),
        'portal_status' => 'success',
        'proposal_status' => 'success',
        'status' => 'info',
        'warning' => 'warning',
        'error' => 'danger',
    ] as $key => $tone) {
        if (session($key)) {
            $messages[] = ['tone' => $tone, 'text' => session($key)];
        }
    }

    if ($errors->any()) {
        $messages[] = [
            'tone' => 'danger',
            'text' => $errors->first(),
            'items' => $errors->all(),
        ];
    }
@endphp

@if($messages)
    <div class="app-messages" aria-live="polite">
        @foreach($messages as $message)
            <section
                class="app-message app-message--{{ $message['tone'] }}"
                role="status"
                data-dismissible-message
                data-dismiss-timeout="6000"
            >
                <button class="app-message__close" type="button" aria-label="Fechar notificacao" data-dismiss-message>
                    <span aria-hidden="true">x</span>
                </button>

                <div class="app-message__content">
                    <strong>
                        @switch($message['tone'])
                            @case('success') Tudo certo @break
                            @case('warning') Atencao @break
                            @case('danger') Verifique @break
                            @default Informacao
                        @endswitch
                    </strong>
                    <span>{{ $message['text'] }}</span>

                    @if(isset($message['items']) && count($message['items']) > 1)
                        <ul>
                            @foreach($message['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <span class="app-message__timer" aria-hidden="true"></span>
            </section>
        @endforeach
    </div>
@endif
