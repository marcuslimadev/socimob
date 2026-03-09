<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.5; }
    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 20px; }
    .header h1 { font-size: 16px; text-transform: uppercase; }
    .section { margin-bottom: 18px; }
    .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; background: #f0f0f0; padding: 4px 8px; border-left: 3px solid #333; margin-bottom: 8px; }
    .row { display: flex; gap: 12px; margin-bottom: 4px; }
    .field { flex: 1; }
    .field label { font-size: 9px; text-transform: uppercase; color: #666; display: block; }
    .field span { font-size: 11px; font-weight: bold; }
    .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>
<div class="header">
    <h1>Aditivo Contratual</h1>
    <p>Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }} — Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
</div>

<div class="section">
    <div class="section-title">Partes</div>
    <div class="row">
        <div class="field"><label>Locador</label><span>{{ $locador->nome ?? '—' }}</span></div>
        <div class="field"><label>Locatário</label><span>{{ $locatario->nome ?? '—' }}</span></div>
    </div>
</div>

<div class="section">
    <div class="section-title">Objeto do Aditivo</div>
    <p style="padding:10px;border:1px solid #ddd;border-radius:4px;background:#fafafa;font-size:11px;">
        As partes identificadas acima acordam em aditar o contrato de locação acima referenciado,
        conforme condições descritas e negociadas entre as partes.
    </p>
</div>

<div class="signatures" style="margin-top:60px;display:flex;gap:30px;">
    <div class="sig-block" style="flex:1;text-align:center;">
        <div style="border-top:1px solid #333;padding-top:4px;font-size:10px;margin-top:40px;">{{ $locador->nome ?? 'Locador' }}</div>
        <p style="font-size:9px;color:#666;">Locador</p>
    </div>
    <div class="sig-block" style="flex:1;text-align:center;">
        <div style="border-top:1px solid #333;padding-top:4px;font-size:10px;margin-top:40px;">{{ $locatario->nome ?? 'Locatário' }}</div>
        <p style="font-size:9px;color:#666;">Locatário</p>
    </div>
</div>

<div class="footer">
    Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }}
</div>
</body>
</html>
