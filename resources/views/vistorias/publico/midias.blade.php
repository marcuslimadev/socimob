<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mídias da vistoria</title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;background:#f7fafc;color:#172033}.wrap{max-width:1040px;margin:0 auto;padding:24px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:14px 0}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}img,video{width:100%;border-radius:6px;border:1px solid #e5e7eb}.btn{display:inline-block;background:#1f4e79;color:#fff;padding:10px 14px;border-radius:6px;text-decoration:none}
    </style>
</head>
<body><div class="wrap">
    <h1>Mídias da vistoria {{ $vistoria->codigo ?? '#'.$vistoria->id }}</h1>
    <p>{{ $vistoria->cliente_nome }} | {{ optional($vistoria->data_vistoria)->format('d/m/Y H:i') }}</p>
    <p><a class="btn" href="{{ $contestacaoUrl }}">Realizar contestação</a> <a class="btn" href="/vistorias/publico/{{ $vistoria->link_publico_midias_token }}/pdf">Baixar PDF</a></p>
    @foreach($vistoria->ambientes as $ambiente)
        <section class="card">
            <h2>{{ $ambiente->nome }}</h2>
            <p>{{ $ambiente->observacoes }}</p>
            <div class="grid">
                @foreach($ambiente->midias as $midia)
                    <div>
                        @if(str_starts_with((string) $midia->mime_type, 'video/'))
                            <video src="{{ $midia->url }}" controls></video>
                        @else
                            <img src="{{ $midia->url }}" alt="">
                        @endif
                        <p>{{ $midia->legenda }}</p>
                        <a href="{{ $midia->url }}" download>Download</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div></body></html>
