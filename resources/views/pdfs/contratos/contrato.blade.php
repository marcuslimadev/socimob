<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.6; }
    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 14px; margin-bottom: 22px; }
    .header h1 { font-size: 15px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
    .header p  { font-size: 10px; margin-top: 4px; color: #555; }
    .intro-texto { margin-bottom: 18px; font-size: 11px; line-height: 1.7; text-align: justify; }
    .section { margin-bottom: 18px; }
    .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; background: #f0f0f0; padding: 4px 8px; border-left: 3px solid #333; margin-bottom: 8px; }
    .row { display: flex; gap: 12px; margin-bottom: 4px; }
    .field { flex: 1; }
    .field label { font-size: 9px; text-transform: uppercase; color: #666; display: block; }
    .field span  { font-size: 11px; font-weight: bold; }
    .table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
    .table th { background: #333; color: #fff; padding: 5px 8px; text-align: left; }
    .table td { padding: 4px 8px; border-bottom: 1px solid #ddd; }
    .table tr:nth-child(even) td { background: #f9f9f9; }
    .clausulas { margin-top: 8px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #fafafa; font-size: 10px; }
    .clausula-item { margin-bottom: 8px; text-align: justify; }
    .clausula-item:last-child { margin-bottom: 0; }
    .signatures { margin-top: 50px; display: flex; gap: 20px; justify-content: space-around; }
    .sig-block { flex: 1; text-align: center; max-width: 180px; }
    .sig-line { border-top: 1px solid #333; padding-top: 4px; font-size: 10px; margin-top: 50px; }
    .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>

{{-- CABEÇALHO --}}
<div class="header">
    <h1>{{ $tenantTemplate?->titulo ?? 'Contrato de Locação Residencial' }}</h1>
    <p>Contrato Nº {{ $contrato->numero_contrato ?? $contrato->id }} — Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
</div>

{{-- TEXTO DE ABERTURA personalizado pelo tenant --}}
@if(!empty($tenantTemplate?->intro_texto))
<div class="intro-texto">
    {!! nl2br(e($tenantTemplate->intro_texto)) !!}
</div>
@endif

{{-- LOCADOR --}}
<div class="section">
    <div class="section-title">Locador (Proprietário)</div>
    <div class="row">
        <div class="field"><label>Nome</label><span>{{ $locador->nome ?? '—' }}</span></div>
        <div class="field"><label>CPF/CNPJ</label><span>{{ $locador->cpf ?? $locador->cnpj ?? '—' }}</span></div>
    </div>
    <div class="row">
        <div class="field"><label>E-mail</label><span>{{ $locador->email ?? '—' }}</span></div>
        <div class="field"><label>Telefone</label><span>{{ $locador->telefone ?? $locador->whatsapp ?? '—' }}</span></div>
    </div>
</div>

{{-- LOCATÁRIO --}}
<div class="section">
    <div class="section-title">Locatário (Inquilino)</div>
    <div class="row">
        <div class="field"><label>Nome</label><span>{{ $locatario->nome ?? '—' }}</span></div>
        <div class="field"><label>CPF/CNPJ</label><span>{{ $locatario->cpf ?? $locatario->cnpj ?? '—' }}</span></div>
    </div>
    <div class="row">
        <div class="field"><label>E-mail</label><span>{{ $locatario->email ?? '—' }}</span></div>
        <div class="field"><label>Telefone</label><span>{{ $locatario->telefone ?? $locatario->whatsapp ?? '—' }}</span></div>
    </div>
</div>

{{-- FIADORES --}}
@if($fiadores && $fiadores->isNotEmpty())
<div class="section">
    <div class="section-title">Fiadores / Garantias</div>
    <table class="table">
        <thead><tr><th>Nome</th><th>CPF/CNPJ</th><th>Tipo</th></tr></thead>
        <tbody>
        @foreach($fiadores as $f)
        <tr>
            <td>{{ $f->pessoa->nome ?? '—' }}</td>
            <td>{{ $f->pessoa->cpf ?? $f->pessoa->cnpj ?? '—' }}</td>
            <td>{{ ucfirst($f->tipo_vinculo ?? 'Fiador') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- IMÓVEL --}}
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

{{-- CONDIÇÕES FINANCEIRAS --}}
<div class="section">
    <div class="section-title">Condições Financeiras</div>
    <div class="row">
        <div class="field"><label>Vigência</label><span>{{ $contrato->inicio?->format('d/m/Y') }} a {{ $contrato->fim?->format('d/m/Y') }}</span></div>
        <div class="field"><label>Vencimento</label><span>Dia {{ $contrato->dia_vencimento }}</span></div>
    </div>
    <div class="row">
        <div class="field"><label>Valor do Aluguel</label><span>R$ {{ number_format($contrato->valor_aluguel, 2, ',', '.') }}</span></div>
        @if($contrato->valor_condominio)
        <div class="field"><label>Condomínio</label><span>R$ {{ number_format($contrato->valor_condominio, 2, ',', '.') }}</span></div>
        @endif
        @if($contrato->valor_iptu)
        <div class="field"><label>IPTU (mensal)</label><span>R$ {{ number_format($contrato->valor_iptu, 2, ',', '.') }}</span></div>
        @endif
    </div>
    <div class="row">
        <div class="field"><label>Tipo de Garantia</label><span>{{ ucfirst(str_replace('_', ' ', $contrato->tipo_garantia ?? '—')) }}</span></div>
        @if($contrato->valor_garantia)
        <div class="field"><label>Valor Garantia</label><span>R$ {{ number_format($contrato->valor_garantia, 2, ',', '.') }}</span></div>
        @endif
        @if($contrato->indice_reajuste)
        <div class="field"><label>Índice de Reajuste</label><span>{{ strtoupper($contrato->indice_reajuste) }} / {{ $contrato->periodicidade_reajuste ?? 12 }} meses</span></div>
        @endif
    </div>
</div>

{{-- CLÁUSULAS PADRÃO DO TENANT --}}
@if(!empty($tenantTemplate?->clausulas_padrao) && count($tenantTemplate->clausulas_padrao) > 0)
<div class="section">
    <div class="section-title">Cláusulas e Condições</div>
    <div class="clausulas">
        @foreach($tenantTemplate->clausulas_padrao as $i => $clausula)
        <div class="clausula-item"><strong>{{ $i + 1 }}.</strong> {{ $clausula }}</div>
        @endforeach
    </div>
</div>
@endif

{{-- CLÁUSULAS ESPECÍFICAS DO CONTRATO --}}
@if($contrato->clausulas && count($contrato->clausulas) > 0)
<div class="section">
    <div class="section-title">Cláusulas Específicas deste Contrato</div>
    <div class="clausulas">
        @foreach($contrato->clausulas as $i => $clausula)
        <div class="clausula-item"><strong>{{ $i + 1 }}.</strong> {{ $clausula }}</div>
        @endforeach
    </div>
</div>
@endif

{{-- OBSERVAÇÕES --}}
@if($contrato->observacoes)
<div class="section">
    <div class="section-title">Observações</div>
    <div class="clausulas">{{ $contrato->observacoes }}</div>
</div>
@endif

{{-- ASSINATURAS --}}
<div class="signatures">
    <div class="sig-block">
        <div class="sig-line">{{ $locador->nome ?? 'Locador' }}</div>
        <p style="font-size:9px;color:#666;margin-top:2px;">Locador</p>
    </div>
    <div class="sig-block">
        <div class="sig-line">{{ $locatario->nome ?? 'Locatário' }}</div>
        <p style="font-size:9px;color:#666;margin-top:2px;">Locatário</p>
    </div>
    @foreach($fiadores ?? [] as $f)
    <div class="sig-block">
        <div class="sig-line">{{ $f->pessoa->nome ?? 'Fiador' }}</div>
        <p style="font-size:9px;color:#666;margin-top:2px;">Fiador</p>
    </div>
    @endforeach
</div>

{{-- RODAPÉ --}}
<div class="footer">
    @if(!empty($tenantTemplate?->rodape_texto))
        {{ $tenantTemplate->rodape_texto }}
    @else
        Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }}
    @endif
</div>

</body>
</html>
