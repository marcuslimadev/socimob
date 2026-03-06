<?php

/**
 * Update Exclusiva tenant with real company data from exclusivalarimoveis.com.br
 * Run: php database/seeders/UpdateExclusivaData.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Laravel\Lumen\Bootstrap\LoadEnvironmentVariables;

(new LoadEnvironmentVariables(dirname(__DIR__, 2)))->bootstrap();

try {
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE', 'exclusiva'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Atualizando dados da Exclusiva Lar Imoveis...\n\n";

    // Check if tenant exists
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug = ? OR domain LIKE '%exclusiva%' LIMIT 1");
    $stmt->execute(['exclusiva']);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        echo "Tenant Exclusiva nao encontrado!\n";
        exit(1);
    }

    $tenantId = $tenant['id'];
    echo "Tenant encontrado (ID: {$tenantId})\n";

    // Real company data from exclusivalarimoveis.com.br
    $services = json_encode([
        'Venda de Imoveis',
        'Locacao de Imoveis',
        'Avaliacao de Imovel',
        'Financiamento Imobiliario',
        'Correspondente Bancario',
        'Administracao de Imovel',
    ]);

    $socialLinks = json_encode([
        'facebook' => 'https://www.facebook.com/exclusivalarimoveis',
        'instagram' => 'https://www.instagram.com/exclusivalarimoveis',
    ]);

    $neighborhoods = json_encode([
        'Belo Horizonte' => ['Itapoa', 'Santa Ines', 'Rio Branco', 'Dona Clara', 'Ouro Preto', 'Castelo', 'Pampulha', 'Jardim Atlantico', 'Alipes', 'Santa Amelia', 'Planalto', 'Serrano', 'Liberdade', 'Floresta', 'Santa Efigenia', 'Centro', 'Serra', 'Funcionarios', 'Lourdes', 'Savassi', 'Santo Antonio', 'Sion', 'Sagrada Familia', 'Carlos Prates', 'Padre Eustaquio', 'Caicaras', 'Coracao Eucaristico', 'Calcada', 'Jardim America', 'Cidade Nova', 'Concordia', 'Nacional', 'Renascenca', 'Santa Cruz', 'Venda Nova', 'Mantiqueira'],
        'Contagem' => ['Eldorado', 'Cabral', 'Industrial', 'Cinco', 'Gloria', 'Ressaca', 'Inconfidentes'],
        'Santa Luzia' => ['Palmital', 'Sao Benedito', 'Boa Esperanca'],
        'Sabara' => ['Caieiras', 'General Carneiro', 'Ana Lucia'],
        'Lagoa Santa' => ['Centro', 'Joana Darc', 'Lundceia'],
        'Betim' => ['Centro', 'Alterosa', 'Jardim Teresopolis'],
        'Ribeirao das Neves' => ['Centro', 'Veneza', 'Florenca'],
        'Pedro Leopoldo' => ['Centro', 'Lagoa de Santo Antonio'],
        'Matozinhos' => ['Centro'],
        'Esmeraldas' => ['Centro', 'Menezes'],
        'Ibirite' => ['Centro', 'Durval de Barros'],
        'Nova Lima' => ['Jardim Canada', 'Vila da Serra', 'Centro'],
    ]);

    $aboutText = 'A Exclusiva Lar Imoveis e uma empresa do ramo imobiliario que atua na regiao metropolitana de Belo Horizonte. Fundada com o objetivo de oferecer um atendimento diferenciado e personalizado, a empresa conta com profissionais qualificados e experientes para atender as necessidades de seus clientes, seja na compra, venda, locacao ou avaliacao de imoveis.';

    $metadata = json_encode([
        'features' => ['crm', 'whatsapp', 'portal', 'analytics', 'avaliacao'],
        'integrations' => ['apm_imoveis', 'neca'],
        'created_by' => 'seed',
        'phones' => [
            'principal' => '(31) 97559-7278',
            'fixo' => '(31) 3665-0338',
            'locacao' => '(31) 98306-3324',
        ],
        'address' => [
            'logradouro' => 'Rua Sao Miguel, 1523',
            'bairro' => 'Itapoa',
            'cidade' => 'Belo Horizonte',
            'estado' => 'MG',
            'cep' => '31710-400',
        ],
    ]);

    // Update tenant record
    $sql = "UPDATE tenants SET
        name = ?,
        razao_social = ?,
        contact_email = ?,
        contact_phone = ?,
        description = ?,
        about_text = ?,
        slogan = ?,
        mascot_url = ?,
        creci = ?,
        endereco = ?,
        services = ?,
        social_links = ?,
        neighborhoods = ?,
        office_hours = ?,
        metadata = ?,
        updated_at = NOW()
    WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'Exclusiva Lar Imoveis',
        'Exclusiva Lar Imoveis LTDA',
        'corretora.alexsandrafialho@gmail.com',
        '(31) 97559-7278',
        'Imobiliaria especializada na regiao metropolitana de Belo Horizonte',
        $aboutText,
        'Seu imóvel dos sonhos esta aqui',
        '/assets/turtle.png',
        '006994',
        'Rua Sao Miguel, 1523 - Itapoa, Belo Horizonte/MG',
        $services,
        $socialLinks,
        $neighborhoods,
        'Segunda a Sexta: 9h - 18h | Sabado: 9h - 13h',
        $metadata,
        $tenantId,
    ]);

    echo "Tenant atualizado com dados reais da Exclusiva!\n";

    // Update tenant_config whatsapp_number
    $stmt = $pdo->prepare("SELECT id FROM tenant_configs WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $config = $stmt->fetch();

    if ($config) {
        $stmt = $pdo->prepare("UPDATE tenant_configs SET whatsapp_number = ?, updated_at = NOW() WHERE tenant_id = ?");
        $stmt->execute(['5531975597278', $tenantId]);
        echo "WhatsApp number atualizado na config!\n";
    }

    echo "\nDados atualizados com sucesso!\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
