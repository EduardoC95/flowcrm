<!DOCTYPE html>
<html lang="pt">
    <body>
        <h1>{{ $event->title }}</h1>
        <p>Este é o lembrete configurado no FlowCRM.</p>
        <p>
            <strong>Início:</strong>
            {{ $event->start_at?->format('Y-m-d H:i') }}
        </p>
        @if ($event->location)
            <p>
                <strong>Localização:</strong>
                {{ $event->location }}
            </p>
        @endif
        @if ($event->description)
            <p>{{ $event->description }}</p>
        @endif
    </body>
</html>
