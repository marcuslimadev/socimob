<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.5; }
    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 20px; }
    .header h1 { font-size: 16px; text-transform: uppercase; letter-spacing: 1px; }
    .header p { font-size: 10px; margin-top: 4px; }
    .section { margin-bottom: 18px; }
    .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; background: #f0f0f0; padding: 4px 8px; border-left: 3px solid #333; margin-bottom: 8px; }
    .row { display: flex; gap: 12px; margin-bottom: 4px; }
    .field { flex: 1; }
    .field label { font-size: 9px; text-transform: uppercase; color: #666; display: block; }
    .field span { font-size: 11px; font-weight: bold; }
    .table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
    .table th { background: #333; color: #fff; padding: 5px 8px; text-align: left; }
    .table td { padding: 4px 8px; border-bottom: 1px solid #ddd; }
    .table tr:nth-child(even) td { background: #f9f9f9; }
    .clausulas { margin-top: 8px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #fafafa; font-size: 10px; }
    .signatures { margin-top: 40px; display: flex; gap: 30px; }
    .sig-block { flex: 1; text-align: center; }
    .sig-line { border-top: 1px solid #333; padding-top: 4px; font-size: 10px; margin-top: 40px; }
    .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>
<div class="header">
    <h1>Contrato de Locação Residencial</h1>
    <p>Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }} — Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
</div>

<div class="section">
    <div class="section-title">Locador (Proprietário)</div>
    <div class="row">
        <div class="field"><label>Nome</label><span>{{ $locador->nome ?? '—' }}</span></div>
        <div class="field"><label>CPF/CNPJ</label><span>{{ $locador->cpf_cnpj ?? '—' }}</span></div>
    </div>
    <div class="row">
        <div class="field"><label>E-mail</label><span>{{ $locador->email ?? '—' }}</span></div>
        <div class="field"><label>Telefone</label><span>{{ $locador->telefone ?? '—' }}</span></div>
    </div>
</div>

<div class="section">
    <div class="section-title">Locatário (Inquilino)</div>
    <div class="row">
        <div class="field"><label>Nome</label><span>{{ $locatario->nome ?? '—' }}</span></div>
        <div class="field"><label>CPF/CNPJ</label><span>{{ $locatario->cpf_cnpj ?? '—' }}</span></div>
    </div>
    <div class="row">
        <div class="field"><label>E-mail</label><span>{{ $locatario->email ?? '—' }}</span></div>
        <div class="field"><label>Telefone</label><span>{{ $locatario->telefone ?? '—' }}</span></div>
    </div>
</div>

@if($fiadores && $fiadores->isNotEmpty())
<div class="section">
    <div class="section-title">Fiadores / Garantias</div>
    <table class="table">
        <thead><tr><th>Nome</th><th>CPF/CNPJ</th><th>Tipo</th></tr></thead>
        <tbody>
        @foreach($fiadores as $f)
        <tr>
            <td>{{ $f->pessoa->nome ?? '—' }}</td>
            <td>{{ $f->pessoa->cpf_cnpj ?? '—' }}</td>
            <td>{{ ucfirst($f->tipo_vinculo ?? 'fiador') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="section">
    <div class="section-title">Imóvel Locado</div>
    <div class="row">
        <div class="field"><label>Endereço</label><span>{{ $imovel->endereco ?? '—' }}</span></div>
        <div class="field"><label>Código</label><span>{{ $imovel->codigo ?? '—' }}</span></div>
    </div>
    <div class="row">
        <div class="field"><label>Cidade/UF</label><span>{{ $imovel->cidade ?? '—' }}/{{ $imovel->estado ?? '—' }}</span></div>
        <div class="field"><label>CEP</label><span>{{ $imovel->cep ?? '—' }}</span></div>
    </div>
</div>

<div class="section">
    <div class="section-title">Condições Financeiras</div>
    <div class="row">
        <div class="field"><label>Vigência</label><span>{{ $contrato->inicio?->format('d/m/Y') }} a {{ $contrato->fim?->format('d/m/Y') }}</span></div>
        <div class="field"><label>Vencimento</label><span>Dia {{ $contrato->dia_vencimento }}</span></div>
    </div>
    <div class="row">
        <div class="field"><label>Valor do Aluguel</label><span>R$ {{ number_format($contrato->valor_aluguel, 2, ',', '.') }}</span></div>
        <div class="field"><label>Condomínio</label><span>R$ {{ number_format($contrato->valor_condominio ?? 0, 2, ',', '.') }}</span></div>
        <div class="field"><label>IPTU</label><span>R$ {{ number_format($contrato->valor_iptu ?? 0, 2, ',', '.') }}</span></div>
    </div>
    <div class="row">
        <div class="field"><label>Garantia</label><span>{{ ucfirst($contrato->tipo_garantia ?? '—') }}</span></div>
        @if($contrato->valor_garantia)
        <div class="field"><label>Valor Garantia</label><span>R$ {{ number_format($contrato->valor_garantia, 2, ',', '.') }}</span></div>
        @endif
        <div class="field"><label>Reajuste</label><span>{{ strtoupper($contrato->indice_reajuste ?? '—') }}</span></div>
    </div>
</div>

@if($contrato->clausulas)
<div class="section">
    <div class="section-title">Cláusulas Especiais</div>
    <div class="clausulas">
        @foreach($contrato->clausulas as $clausula)
        <p style="margin-bottom: 6px;">{{ $clausula }}</p>
        @endforeach
    </div>
</div>
@endif

@if($contrato->observacoes)
<div class="section">
    <div class="section-title">Observações</div>
    <div class="clausulas">{{ $contrato->observacoes }}</div>
</div>
@endif

<div class="signatures">
    <div class="sig-block">
        <div class="sig-line">{{ $locador->nome ?? 'Locador' }}</div>
        <p style="font-size:9px;color:#666;">Locador</p>
    </div>
    <div class="sig-block">
        <div class="sig-line">{{ $locatario->nome ?? 'Locatário' }}</div>
        <p style="font-size:9px;color:#666;">Locatário</p>
    </div>
    @foreach($fiadores ?? [] as $f)
    <div class="sig-block">
        <div class="sig-line">{{ $f->pessoa->nome ?? 'Fiador' }}</div>
        <p style="font-size:9px;color:#666;">Fiador</p>
    </div>
    @endforeach
</div>

<div class="footer">
    Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }}
</div>
</body>
</html>
