<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mídias da vistoria {{ $vistoria->codigo ?? '#'.$vistoria->id }}</title>
    <style>
        :root{--brand:#1f4e79;--ink:#172033;--muted:#64748b;--line:#dbe3ea;--bg:#f4f7fb}
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;background:var(--bg);color:var(--ink)}.wrap{max-width:1120px;margin:0 auto;padding:28px 18px}.hero{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:16px}.eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:700}h1{margin:6px 0 8px;font-size:28px}.meta{color:var(--muted);line-height:1.5}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}.btn{display:inline-block;background:var(--brand);color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:700}.btn.secondary{background:#eef4fa;color:var(--brand);border:1px solid #c9d9e8}.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px;margin:14px 0}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px}.media{border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}.media img,.media video{width:100%;height:160px;object-fit:cover;display:block;background:#e2e8f0}.caption{padding:10px;font-size:13px;color:var(--muted)}.empty{border:1px dashed var(--line);border-radius:10px;padding:18px;color:var(--muted);text-align:center}.pill{display:inline-block;border:1px solid var(--line);border-radius:999px;padding:4px 9px;font-size:12px;color:var(--muted);margin-top:4px}
    </style>
</head>
<body>
@php
    $fotosPorComodo = $vistoria->fotos->groupBy(fn ($foto) => mb_strtolower(trim((string) ($foto->comodo ?: 'Sem compartimento'))));
    $renderizados = collect();
@endphp
<div class="wrap">
    <section class="hero">
        <div class="eyebrow">Galeria pública protegida por token</div>
        <h1>Mídias da vistoria {{ $vistoria->codigo ?? '#'.$vistoria->id }}</h1>
        <div class="meta">
            {{ $vistoria->cliente_nome ?: 'Cliente não informado' }}<br>
            {{ optional($vistoria->data_vistoria ?: $vistoria->data_inicio ?: $vistoria->data_agendada)->format('d/m/Y H:i') ?: 'Data não informada' }}
        </div>
        <div class="actions">
            <a class="btn" href="{{ $contestacaoUrl }}">Realizar contestação</a>
            <a class="btn secondary" href="/api/vistorias/publico/{{ $vistoria->link_publico_midias_token }}/pdf">Baixar laudo PDF</a>
        </div>
    </section>

    @forelse($vistoria->ambientes as $ambiente)
        @php
            $key = mb_strtolower(trim((string) $ambiente->nome));
            $renderizados->push($key);
            $fotosLegadas = $fotosPorComodo->get($key, collect());
        @endphp
        <section class="card">
            <h2>{{ $ambiente->nome }}</h2>
            @if($ambiente->observacoes)<p class="meta">{{ $ambiente->observacoes }}</p>@endif
            <span class="pill">{{ $ambiente->midias->count() + $fotosLegadas->count() }} mídia(s)</span>
            @if($ambiente->midias->count() || $fotosLegadas->count())
                <div class="grid" style="margin-top:14px">
                    @foreach($ambiente->midias as $midia)
                        <div class="media">
                            @if(str_starts_with((string) $midia->mime_type, 'video/'))
                                <video src="{{ $midia->url }}" controls></video>
                            @else
                                <img src="{{ $midia->url }}" alt="">
                            @endif
                            <div class="caption">{{ $midia->legenda ?: 'Sem legenda' }}<br><a href="{{ $midia->url }}" download>Download</a></div>
                        </div>
                    @endforeach
                    @foreach($fotosLegadas as $foto)
                        <div class="media">
                            <img src="{{ $foto->url_signed ?: $foto->url }}" alt="">
                            <div class="caption">{{ $foto->descricao ?: $foto->legenda ?: 'Sem legenda' }}<br><a href="{{ $foto->url_signed ?: $foto->url }}" download>Download</a></div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty">Nenhuma mídia vinculada a este ambiente.</div>
            @endif
        </section>
    @empty
        <section class="card"><div class="empty">Nenhum ambiente cadastrado.</div></section>
    @endforelse

    @foreach($fotosPorComodo as $key => $fotos)
        @continue($renderizados->contains($key))
        <section class="card">
            <h2>{{ $fotos->first()->comodo ?: 'Sem compartimento' }}</h2>
            <div class="grid">
                @foreach($fotos as $foto)
                    <div class="media">
                        <img src="{{ $foto->url_signed ?: $foto->url }}" alt="">
                        <div class="caption">{{ $foto->descricao ?: $foto->legenda ?: 'Sem legenda' }}<br><a href="{{ $foto->url_signed ?: $foto->url }}" download>Download</a></div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
</body>
</html>
