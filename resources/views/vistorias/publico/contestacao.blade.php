<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contestação da vistoria {{ $vistoria->codigo ?? '#'.$vistoria->id }}</title>
    <style>
        :root{--brand:#1f4e79;--ink:#172033;--muted:#64748b;--line:#dbe3ea;--bg:#f4f7fb;--warn:#9a3412}
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;background:var(--bg);color:var(--ink)}.wrap{max-width:920px;margin:0 auto;padding:28px 18px}.hero,.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:16px}.eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);font-weight:700}h1{margin:6px 0 8px;font-size:28px}.meta{color:var(--muted);line-height:1.5}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}label{display:block;font-size:13px;font-weight:700;color:#334155}input,select,textarea{margin-top:6px;width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px;font:inherit}textarea{resize:vertical}.full{grid-column:1/-1}.btn{background:var(--brand);color:#fff;border:0;padding:12px 16px;border-radius:8px;margin-top:14px;font-weight:700;cursor:pointer}.alert{background:#fff7ed;border:1px solid #fed7aa;color:var(--warn);padding:14px;border-radius:10px}.success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:14px;border-radius:10px}.hint{font-size:12px;color:var(--muted);font-weight:400;margin-top:4px}@media(max-width:720px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <section class="hero">
        <div class="eyebrow">Contestação pública protegida por token</div>
        <h1>Contestação de vistoria</h1>
        <div class="meta">
            Vistoria {{ $vistoria->codigo ?? '#'.$vistoria->id }}<br>
            Prazo: {{ optional($vistoria->data_limite_contestacao)->format('d/m/Y H:i') ?: 'não definido' }}
        </div>
    </section>

    @if(session('success'))<div class="success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert">
            Revise os dados informados e tente novamente.
        </div>
    @endif

    @if($prazoExpirado)
        <div class="alert">O prazo de contestação expirou. Não é mais possível enviar novas contestações por este link.</div>
    @else
        <form class="card" method="post" enctype="multipart/form-data">
            @csrf
            <div class="grid">
                <label>Nome
                    <input name="nome" value="{{ old('nome') }}" required>
                </label>
                <label>Documento
                    <input name="documento" value="{{ old('documento') }}">
                </label>
                <label>Email
                    <input name="email" type="email" value="{{ old('email') }}">
                </label>
                <label>Telefone
                    <input name="telefone" value="{{ old('telefone') }}">
                </label>
                <label>Ambiente
                    <select name="ambiente_id">
                        <option value="">Contestação geral</option>
                        @foreach($vistoria->ambientes as $ambiente)
                            <option value="{{ $ambiente->id }}" @selected(old('ambiente_id') == $ambiente->id)>{{ $ambiente->nome }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Item ou inconformidade
                    <select name="item_id">
                        <option value="">Não vincular item específico</option>
                        @foreach($vistoria->ambientes as $ambiente)
                            @foreach($ambiente->itens as $item)
                                <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>{{ $ambiente->nome }} - {{ $item->nome }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </label>
                <label class="full">Contestação
                    <textarea name="texto" rows="7" required placeholder="Descreva exatamente o que discorda no laudo e onde está a divergência.">{{ old('texto') }}</textarea>
                    <div class="hint">Inclua ambiente, item e uma descrição objetiva. Anexe fotos ou vídeos abaixo.</div>
                </label>
                <label class="full">Fotos ou vídeos
                    <input name="midias[]" type="file" multiple accept="image/*,video/*">
                    <div class="hint">Os anexos serão vinculados à contestação e ficarão disponíveis para análise da imobiliária.</div>
                </label>
            </div>
            <button class="btn" type="submit">Enviar contestação</button>
        </form>
    @endif
</div>
</body>
</html>
