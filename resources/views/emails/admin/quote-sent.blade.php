<p>Segue em anexo o orcamento <strong>{{ $quote->number }}</strong>.</p>

@if (! empty($messageBody))
    <p>{!! nl2br(e($messageBody)) !!}</p>
@endif

<p>Com os melhores cumprimentos,</p>
<p>{{ setting('app.name', (string) config('app.name')) }}</p>

