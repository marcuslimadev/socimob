<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.5; }
    .header { text-align: center; border-bottom: 2px solid #2b6cb0; padding-bottom: 12px; margin-bottom: 20px; }
    .header h1 { font-size: 16px; text-transform: uppercase; letter-spacing: 1px; color: #2b6cb0; }
    .section { margin-bottom: 18px; }
    .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; background: #f0f0f0; padding: 4px 8px; border-left: 3px solid #2b6cb0; margin-bottom: 8px; }
    .row { display: flex; gap: 12px; margin-bottom: 4px; }
    .field { flex: 1; }
    .field label { font-size: 9px; text-transform: uppercase; color: #666; display: block; }
    .field span { font-size: 11px; font-weight: bold; }
    .box { border: 1px solid #ddd; border-radius: 4px; padding: 12px; margin: 8px 0; background: #f0f8ff; }
    .signatures { margin-top: 40px; display: flex; gap: 30px; }
    .sig-block { flex: 1; text-align: center; }
    .sig-line { border-top: 1px solid #333; padding-top: 4px; font-size: 10px; margin-top: 40px; }
    .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>
<div class="header">
    <h1>Aditivo de Renovação de Contrato</h1>
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
    <div class="section-title">Imóvel</div>
    <div class="row">
        <div class="field"><label>Endereço</label><span>{{ $imovel->endereco ?? '—' }}</span></div>
    </div>
</div>

<div class="section">
    <div class="section-title">Termos da Renovação</div>
    <div class="box">
        <div class="row">
            <div class="field"><label>Período Anterior</label><span>{{ $contrato->inicio?->format('d/m/Y') }} a {{ $contrato->renovado_ate?->subDay()->format('d/m/Y') ?? $contrato->fim?->format('d/m/Y') }}</span></div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="field"><label>Nova Vigência</label><span>{{ now()->format('d/m/Y') }} a {{ $contrato->fim?->format('d/m/Y') }}</span></div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="field"><label>Novo Valor de Aluguel</label><span>R$ {{ number_format($contrato->valor_aluguel, 2, ',', '.') }}</span></div>
        </div>
    </div>
</div>

<p style="margin:20px 0;font-size:11px;">As partes concordam com a renovação do contrato de locação nos termos descritos acima, mantendo as demais cláusulas originais.</p>

<div class="signatures">
    <div class="sig-block">
        <div class="sig-line">{{ $locador->nome ?? 'Locador' }}</div>
        <p style="font-size:9px;color:#666;">Locador</p>
    </div>
    <div class="sig-block">
        <div class="sig-line">{{ $locatario->nome ?? 'Locatário' }}</div>
        <p style="font-size:9px;color:#666;">Locatário</p>
    </div>
</div>

<div class="footer">
    Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }}
</div>
</body>
</html>
