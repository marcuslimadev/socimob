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
    .valor-box { border: 2px solid #333; border-radius: 4px; padding: 16px; text-align: center; margin: 20px 0; }
    .valor-box .valor { font-size: 24px; font-weight: bold; color: #2b6cb0; }
    .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>
<div class="header">
    <h1>Recibo de Pagamento de Aluguel</h1>
    <p>Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
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
        <div class="field"><label>Contrato</label><span>#{{ $contrato->numero_contrato ?? $contrato->id }}</span></div>
    </div>
</div>

<div class="valor-box">
    <p style="font-size:12px;margin-bottom:4px;">Valor Recebido</p>
    <p class="valor">R$ {{ number_format($contrato->valor_aluguel ?? 0, 2, ',', '.') }}</p>
    <p style="font-size:10px;margin-top:4px;">Referente ao mês de {{ $geradoEm->format('F/Y') }}</p>
</div>

<p style="font-size:11px;margin:16px 0;">Declaro ter recebido a quantia acima do locatário, a título de aluguel do imóvel descrito, dando plena e irrevogável quitação.</p>

<div style="margin-top:50px;text-align:center;">
    <div style="border-top:1px solid #333;display:inline-block;min-width:250px;padding-top:4px;">
        <p style="font-size:10px;">{{ $locador->nome ?? 'Locador' }}</p>
        <p style="font-size:9px;color:#666;">Assinatura do Locador</p>
    </div>
</div>

<div class="footer">
    Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }}
</div>
</body>
</html>
