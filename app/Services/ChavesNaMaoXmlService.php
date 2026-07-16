<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use XMLWriter;

class ChavesNaMaoXmlService
{
    private const PROPERTY_TYPES = [
        'apartamento' => ['RE', 'Apartamento'],
        'casa' => ['RE', 'Casa / Sobrado'],
        'sobrado' => ['RE', 'Casa / Sobrado'],
        'casa_condominio' => ['RE', 'Casa / Sobrado em Condomínio'],
        'cobertura' => ['RE', 'Cobertura'],
        'flat' => ['RE', 'Flat'],
        'kitnet' => ['RE', 'Kitnet / Stúdio'],
        'studio' => ['RE', 'Kitnet / Stúdio'],
        'loft' => ['RE', 'Loft'],
        'chacara' => ['RE', 'Sítio / Chácara'],
        'sitio' => ['RE', 'Sítio / Chácara'],
        'terreno' => ['RE', 'Terreno / Lote'],
        'lote' => ['RE', 'Terreno / Lote'],
        'terreno_condominio' => ['RE', 'Terreno em Condomínio'],
        'casa_comercial' => ['CO', 'Casa / Sobrado Comercial'],
        'sala_comercial' => ['CO', 'Conj. Comercial / Sala'],
        'fazenda' => ['CO', 'Fazenda'],
        'galpao' => ['CO', 'Galpão / Depósito'],
        'barracao' => ['CO', 'Galpão / Depósito'],
        'garagem' => ['CO', 'Garagem'],
        'loja' => ['CO', 'Ponto Comercial'],
        'ponto_comercial' => ['CO', 'Ponto Comercial'],
        'predio' => ['CO', 'Prédio'],
        'terreno_comercial' => ['CO', 'Terreno comercial'],
    ];

    private const PROPERTY_TAGS = [
        'referencia', 'codigo_cliente', 'link_cliente', 'titulo', 'transacao', 'transacao2',
        'finalidade', 'finalidade2', 'destaque', 'tipo', 'tipo2', 'valor', 'valor_locacao',
        'valor_iptu', 'valor_condominio', 'area_total', 'area_util', 'conservacao', 'quartos',
        'suites', 'garagem', 'banheiro', 'closet', 'salas', 'despensa', 'bar', 'cozinha',
        'quarto_empregada', 'escritorio', 'area_servico', 'lareira', 'varanda', 'lavanderia',
        'aceita_pet', 'estado', 'cidade', 'bairro', 'cep', 'endereco', 'numero', 'complemento',
        'esconder_endereco_imovel',
    ];

    public function propertiesForTenant(Tenant $tenant): Collection
    {
        return Property::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('active', true)
            ->where('exibir_imovel', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();
    }

    public function generate(Tenant $tenant, string $baseUrl): array
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);
        $writer->setIndentString('    ');
        $writer->startElement('Document');
        $writer->startElement('imoveis');

        $exported = 0;
        $rejected = [];

        foreach ($this->propertiesForTenant($tenant) as $property) {
            $result = $this->mapProperty($property, $baseUrl);
            if (!$result['valid']) {
                $rejected[] = [
                    'id' => $property->id,
                    'referencia' => $this->reference($property),
                    'errors' => $result['errors'],
                ];
                continue;
            }

            $this->writeProperty($writer, $result['data']);
            $exported++;
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        return [
            'xml' => $writer->outputMemory(),
            'exported' => $exported,
            'rejected' => $rejected,
        ];
    }

    public function mapProperty(Property $property, string $baseUrl): array
    {
        $purpose = (string) $property->finalidade_imovel;
        [$transaction, $transaction2] = match ($purpose) {
            'aluguel', 'temporada', 'aluguel_temporada' => ['L', ''],
            'venda_aluguel' => ['V', 'L'],
            default => ['V', ''],
        };

        $type = self::PROPERTY_TYPES[$this->normalizeKey((string) $property->tipo_imovel)] ?? null;
        $reference = $this->reference($property);
        $price = $transaction === 'L'
            ? (float) $property->valor_aluguel
            : (float) $property->valor_venda;
        $features = $this->selectionValues($property->classificacoes);

        $data = [
            'referencia' => $reference,
            'codigo_cliente' => $reference,
            'link_cliente' => rtrim($baseUrl, '/') . '/portal/imovel/' . $property->id,
            'titulo' => $property->titulo,
            'transacao' => $transaction,
            'transacao2' => $transaction2,
            'finalidade' => $type[0] ?? '',
            'finalidade2' => '',
            'destaque' => $property->destaque ? '1' : '0',
            'tipo' => $type[1] ?? '',
            'tipo2' => '',
            'valor' => $this->decimal($price),
            'valor_locacao' => $transaction2 === 'L' ? $this->decimal($property->valor_aluguel) : '',
            'valor_iptu' => $this->decimal($property->valor_iptu),
            'valor_condominio' => $this->decimal($property->valor_condominio),
            'area_total' => $this->decimal($property->area_total),
            'area_util' => $this->decimal($property->area_privativa),
            'conservacao' => '',
            'quartos' => $this->integer($property->dormitorios),
            'suites' => $this->integer($property->suites),
            'garagem' => $this->integer($property->garagem),
            'banheiro' => $this->integer($property->banheiros),
            'closet' => '', 'salas' => '', 'despensa' => '', 'bar' => '', 'cozinha' => '',
            'quarto_empregada' => '', 'escritorio' => '', 'area_servico' => '', 'lareira' => '',
            'varanda' => '', 'lavanderia' => '',
            'aceita_pet' => in_array('aceita_pets', $features, true) ? '1' : '0',
            'estado' => strtoupper(trim((string) $property->estado)),
            'cidade' => trim((string) $property->cidade),
            'bairro' => trim((string) $property->bairro),
            'cep' => trim((string) $property->cep),
            'endereco' => trim((string) $property->logradouro),
            'numero' => trim((string) $property->numero),
            'complemento' => mb_substr(trim((string) $property->complemento), 0, 50),
            'esconder_endereco_imovel' => $property->visibilidade_endereco === 'completo' ? '0' : '1',
            'descritivo' => mb_substr(trim((string) $property->descricao), 0, 3000),
            'fotos' => $this->imageUrls($property, $baseUrl),
            'data_atualizacao' => optional($property->updated_at)->format('Y-m-d H:i:s') ?: '',
            'latitude' => $property->latitude !== null ? (string) $property->latitude : '',
            'longitude' => $property->longitude !== null ? (string) $property->longitude : '',
            'video' => '',
            'tour_360' => '',
            'area_comum' => [],
            'area_privativa' => [],
            'aceita_troca' => in_array('aceita_permuta', $features, true) ? '1' : '0',
            'periodo_locacao' => in_array($purpose, ['temporada', 'aluguel_temporada'], true) ? '2' : '',
        ];

        $errors = [];
        foreach (['referencia', 'transacao', 'finalidade', 'tipo', 'estado', 'cidade', 'bairro', 'descritivo'] as $field) {
            if ($data[$field] === '') {
                $errors[] = $field . ' obrigatório';
            }
        }
        if ($price <= 0) {
            $errors[] = 'valor obrigatório e maior que zero';
        }
        if (mb_strlen($data['estado']) !== 2) {
            $errors[] = 'estado deve ser uma UF com 2 caracteres';
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'data' => $data];
    }

    private function writeProperty(XMLWriter $writer, array $data): void
    {
        $writer->startElement('imovel');
        foreach (self::PROPERTY_TAGS as $tag) {
            $writer->writeElement($tag, (string) ($data[$tag] ?? ''));
        }

        $writer->startElement('descritivo');
        $writer->writeCdata(str_replace(']]>', ']] >', $data['descritivo']));
        $writer->endElement();

        $writer->startElement('fotos_imovel');
        foreach ($data['fotos'] as $url) {
            $writer->startElement('foto');
            $writer->writeElement('url', $url);
            $writer->writeElement('data_atualizacao', $data['data_atualizacao']);
            $writer->endElement();
        }
        $writer->endElement();

        foreach (['data_atualizacao', 'latitude', 'longitude', 'video', 'tour_360'] as $tag) {
            $writer->writeElement($tag, (string) $data[$tag]);
        }
        foreach (['area_comum', 'area_privativa'] as $areaTag) {
            $writer->startElement($areaTag);
            foreach ($data[$areaTag] as $item) {
                $writer->writeElement('item', $item);
            }
            $writer->endElement();
        }
        foreach (['aceita_troca', 'periodo_locacao'] as $tag) {
            $writer->writeElement($tag, (string) $data[$tag]);
        }
        $writer->endElement();
    }

    private function reference(Property $property): string
    {
        return trim((string) ($property->codigo_imovel ?: $property->codigo ?: $property->referencia_imovel ?: $property->id));
    }

    private function imageUrls(Property $property, string $baseUrl): array
    {
        $urls = [];
        if ($property->exists) {
            $urls = ImobiBrasilService::getPropertyImageUrls($property);
        } else {
            if (is_string($property->imagem_destaque) && trim($property->imagem_destaque) !== '') {
                $urls[] = $property->imagem_destaque;
            }
            if (is_array($property->imagens)) {
                $urls = array_merge($urls, $property->imagens);
            }
        }

        return collect($urls)
            ->map(function (string $url) use ($baseUrl) {
                if (preg_match('#^https?://#i', $url)) {
                    return $url;
                }
                return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
            })
            ->unique(fn (string $url) => strtolower($url))
            ->values()
            ->all();
    }

    private function selectionValues($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    private function normalizeKey(string $value): string
    {
        return strtolower(str_replace([' ', '-'], '_', trim($value)));
    }

    private function decimal($value): string
    {
        return ($value === null || $value === '' || (float) $value === 0.0)
            ? ''
            : number_format((float) $value, 2, '.', '');
    }

    private function integer($value): string
    {
        return ($value === null || $value === '') ? '' : (string) max(0, (int) $value);
    }
}
