<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.5; }
    .header { text-align: center; border-bottom: 2px solid #c53030; padding-bottom: 12px; margin-bottom: 20px; }
    .header h1 { font-size: 16px; text-transform: uppercase; letter-spacing: 1px; color: #c53030; }
    .header p { font-size: 10px; margin-top: 4px; }
    .section { margin-bottom: 18px; }
    .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; background: #f0f0f0; padding: 4px 8px; border-left: 3px solid #c53030; margin-bottom: 8px; }
    .row { display: flex; gap: 12px; margin-bottom: 4px; }
    .field { flex: 1; }
    .field label { font-size: 9px; text-transform: uppercase; color: #666; display: block; }
    .field span { font-size: 11px; font-weight: bold; }
    .clausulas { margin-top: 8px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #fafafa; font-size: 10px; }
    .box { border: 1px solid #ddd; border-radius: 4px; padding: 12px; margin: 8px 0; background:#fff5f5; }
    .signatures { margin-top: 40px; display: flex; gap: 30px; }
    .sig-block { flex: 1; text-align: center; }
    .sig-line { border-top: 1px solid #333; padding-top: 4px; font-size: 10px; margin-top: 40px; }
    .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>
<div class="header">
    <h1>Termo de Rescisão de Contrato de Locação</h1>
    <p>Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }} — Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
</div>

<div class="section">
    <div class="section-title">Partes</div>
    <div class="row">
        <div class="field"><label>Locador</label><span>{{ $locador->nome ?? '—' }}</span></div>
        <div class="field"><label>CPF/CNPJ</label><span>{{ $locador->cpf_cnpj ?? '—' }}</span></div>
    </div>
    <div class="row">
        <div class="field"><label>Locatário</label><span>{{ $locatario->nome ?? '—' }}</span></div>
        <div class="field"><label>CPF/CNPJ</label><span>{{ $locatario->cpf_cnpj ?? '—' }}</span></div>
    </div>
</div>

<div class="section">
    <div class="section-title">Imóvel</div>
    <div class="row">
        <div class="field"><label>Endereço</label><span>{{ $imovel->endereco ?? '—' }}</span></div>
        <div class="field"><label>Cidade/UF</label><span>{{ $imovel->cidade ?? '—' }}/{{ $imovel->estado ?? '—' }}</span></div>
    </div>
</div>

<div class="section">
    <div class="section-title">Termos da Rescisão</div>
    <div class="box">
        <div class="row">
            <div class="field"><label>Data de Rescisão</label><span>{{ $contrato->rescindido_em?->format('d/m/Y') ?? '—' }}</span></div>
            <div class="field"><label>Vigência Original</label><span>{{ $contrato->inicio?->format('d/m/Y') }} a {{ $contrato->fim?->format('d/m/Y') }}</span></div>
        </div>
        <div class="row" style="margin-top:8px;">
            <div class="field"><label>Motivo</label><span>{{ $contrato->motivo_rescisao ?? '—' }}</span></div>
        </div>
        @if($contrato->multa_rescisao_calculada)
        <div class="row" style="margin-top:8px;">
            <div class="field"><label>Multa Rescisória</label><span>R$ {{ number_format($contrato->multa_rescisao_calculada, 2, ',', '.') }}</span></div>
        </div>
        @endif
    </div>
</div>

<p style="margin:20px 0;font-size:11px;">As partes acima identificadas, de comum acordo, declaram rescindido o contrato de locação sob as condições descritas neste termo.</p>

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
