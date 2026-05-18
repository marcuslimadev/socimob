<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contestação de vistoria</title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;background:#f7fafc;color:#172033}.wrap{max-width:820px;margin:0 auto;padding:24px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:14px 0}label{display:block;font-size:13px;margin-top:12px}input,select,textarea{box-sizing:border-box;width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:6px}button{background:#1f4e79;color:#fff;border:0;padding:12px 16px;border-radius:6px;margin-top:14px}.alert{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:12px;border-radius:6px}
    </style>
</head>
<body><div class="wrap">
    <h1>Contestação de vistoria</h1>
    <p>Vistoria {{ $vistoria->codigo ?? '#'.$vistoria->id }} | Prazo: {{ optional($vistoria->data_limite_contestacao)->format('d/m/Y H:i') ?: 'não definido' }}</p>
    @if(session('success'))<div class="card">{{ session('success') }}</div>@endif
    @if($prazoExpirado)
        <div class="alert">O prazo de contestação expirou.</div>
    @else
        <form class="card" method="post" enctype="multipart/form-data">
            @csrf
            <label>Nome<input name="nome" required></label>
            <label>Documento<input name="documento"></label>
            <label>Email<input name="email" type="email"></label>
            <label>Telefone<input name="telefone"></label>
            <label>Ambiente
                <select name="ambiente_id">
                    <option value="">Geral</option>
                    @foreach($vistoria->ambientes as $ambiente)
                        <option value="{{ $ambiente->id }}">{{ $ambiente->nome }}</option>
                    @endforeach
                </select>
            </label>
            <label>Contestação<textarea name="texto" rows="6" required></textarea></label>
            <label>Fotos ou vídeos<input name="midias[]" type="file" multiple accept="image/*,video/*"></label>
            <button type="submit">Enviar contestação</button>
        </form>
    @endif
</div></body></html>
