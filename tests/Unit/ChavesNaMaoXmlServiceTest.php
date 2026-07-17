<?php

namespace Tests\Unit;

use App\Models\Property;
use App\Models\Tenant;
use App\Services\ChavesNaMaoXmlService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ChavesNaMaoXmlServiceTest extends TestCase
{
    public function test_maps_a_sale_property_to_the_chaves_na_mao_contract(): void
    {
        $property = new Property([
            'codigo_imovel' => 'EX-123',
            'titulo' => 'Apartamento no Centro',
            'tipo_imovel' => 'apartamento',
            'finalidade_imovel' => 'venda',
            'valor_venda' => 450000,
            'descricao' => 'Imóvel pronto para morar.',
            'estado' => 'pr',
            'cidade' => 'Curitiba',
            'bairro' => 'Centro',
            'dormitorios' => 3,
            'suites' => 1,
            'banheiros' => 2,
            'garagem' => 2,
            'destaque' => true,
            'visibilidade_endereco' => 'bairro_cidade',
        ]);
        $property->id = 123;

        $result = (new ChavesNaMaoXmlService())->mapProperty($property, 'https://exclusivalarimoveis.com');

        $this->assertTrue($result['valid']);
        $this->assertSame('EX-123', $result['data']['referencia']);
        $this->assertSame('V', $result['data']['transacao']);
        $this->assertSame('RE', $result['data']['finalidade']);
        $this->assertSame('Apartamento', $result['data']['tipo']);
        $this->assertSame('450000.00', $result['data']['valor']);
        $this->assertSame('1', $result['data']['esconder_endereco_imovel']);
    }

    public function test_rejects_an_unsupported_or_incomplete_property(): void
    {
        $property = new Property([
            'tipo_imovel' => 'outro',
            'finalidade_imovel' => 'venda',
            'valor_venda' => 0,
        ]);
        $property->id = 99;

        $result = (new ChavesNaMaoXmlService())->mapProperty($property, 'https://example.com');

        $this->assertFalse($result['valid']);
        $this->assertContains('tipo obrigatório', $result['errors']);
        $this->assertContains('valor obrigatório e maior que zero', $result['errors']);
    }

    public function test_generates_well_formed_utf8_xml_with_the_required_structure(): void
    {
        $property = new Property([
            'codigo_imovel' => 'EX-XML',
            'titulo' => 'Casa & Sobrado',
            'tipo_imovel' => 'casa',
            'finalidade_imovel' => 'aluguel',
            'valor_aluguel' => 2500,
            'descricao' => 'Descrição com acentuação & segurança XML.',
            'estado' => 'PR',
            'cidade' => 'Curitiba',
            'bairro' => 'Água Verde',
            'imagens' => ['/storage/imoveis/foto-1.jpg'],
        ]);
        $property->id = 321;

        $service = new class(collect([$property])) extends ChavesNaMaoXmlService {
            public function __construct(private Collection $properties) {}

            public function propertiesForTenant(Tenant $tenant): Collection
            {
                return $this->properties;
            }
        };

        $result = $service->generate(new Tenant(['name' => 'Teste']), 'https://example.com');
        $xml = simplexml_load_string($result['xml']);

        $this->assertNotFalse($xml);
        $this->assertSame(1, $result['exported']);
        $this->assertCount(0, $result['rejected']);
        $this->assertSame('EX-XML', (string) $xml->imoveis->imovel->referencia);
        $this->assertSame('https://example.com/storage/imoveis/foto-1.jpg', (string) $xml->imoveis->imovel->fotos_imovel->foto->url);
    }

    public function test_limits_photos_and_removes_unsupported_formats(): void
    {
        $images = array_map(
            fn (int $index) => "/storage/imoveis/foto-{$index}.jpg",
            range(1, 35)
        );
        $images[] = '/storage/imoveis/planta.png';

        $property = new Property([
            'codigo_imovel' => 'EX-FOTOS',
            'titulo' => 'Apartamento com fotos',
            'tipo_imovel' => 'apartamento',
            'finalidade_imovel' => 'venda',
            'valor_venda' => 300000,
            'descricao' => 'Descrição do imóvel.',
            'estado' => 'MG',
            'cidade' => 'Belo Horizonte',
            'bairro' => 'Centro',
            'imagens' => $images,
        ]);
        $property->id = 500;

        $result = (new ChavesNaMaoXmlService())->mapProperty($property, 'https://example.com');

        $this->assertTrue($result['valid']);
        $this->assertCount(30, $result['data']['fotos']);
        $this->assertNotContains('https://example.com/storage/imoveis/planta.png', $result['data']['fotos']);

        $property->imagens = ['/storage/imoveis/planta.png'];
        $converted = (new ChavesNaMaoXmlService())->mapProperty($property, 'https://example.com');
        $this->assertSame(
            'https://example.com/integracoes/chaves-na-mao/imagens/500/current/0.jpg',
            $converted['data']['fotos'][0]
        );
    }
}
