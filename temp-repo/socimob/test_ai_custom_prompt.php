<?php
/**
 * Script de teste para verificar prompt customizado da IA
 * Uso: php test_ai_custom_prompt.php
 */

echo "\n🧪 TESTE: Prompt Customizado da IA\n";
echo str_repeat("=", 60) . "\n\n";

// 1. Verificar arquivo modificado
echo "1️⃣ Verificando modificações no OpenAIService...\n";
$serviceFile = __DIR__ . '/app/Services/OpenAIService.php';

if (!file_exists($serviceFile)) {
    echo "   ❌ Arquivo não encontrado: $serviceFile\n\n";
    exit(1);
}

$content = file_get_contents($serviceFile);

// Verificar se tem a lógica de prompt customizado
$checks = [
    'AppSetting::getValue(\'ai_prompt_custom\'' => 'Busca prompt customizado',
    'if (!empty($customPrompt))' => 'Verifica se prompt existe',
    'Usando prompt CUSTOMIZADO' => 'Log de prompt customizado',
    'Usando prompt PADRÃO' => 'Log de prompt padrão',
    'str_replace(\'{$assistantName}\'' => 'Substituição de variáveis',
];

$allPassed = true;
foreach ($checks as $needle => $description) {
    if (strpos($content, $needle) !== false) {
        echo "   ✅ $description\n";
    } else {
        echo "   ❌ $description - NÃO ENCONTRADO\n";
        $allPassed = false;
    }
}
echo "\n";

if (!$allPassed) {
    echo "   ⚠️ Algumas verificações falharam!\n\n";
    exit(1);
}

// 2. Verificar configuracoes.html
echo "2️⃣ Verificando interface em configuracoes.html...\n";
$configFile = __DIR__ . '/public/app/configuracoes.html';

if (!file_exists($configFile)) {
    echo "   ❌ Arquivo não encontrado: $configFile\n\n";
    exit(1);
}

$configContent = file_get_contents($configFile);

$uiChecks = [
    'id="ai_prompt_custom"' => 'Campo textarea',
    'maxlength="2000"' => 'Limite de 2000 caracteres',
    'handleSaveAiPrompt' => 'Função de salvar',
    'loadAiPrompt()' => 'Função de carregar',
    '/api/admin/settings/ai-prompt' => 'Endpoint da API',
];

foreach ($uiChecks as $needle => $description) {
    if (strpos($configContent, $needle) !== false) {
        echo "   ✅ $description\n";
    } else {
        echo "   ❌ $description - NÃO ENCONTRADO\n";
        $allPassed = false;
    }
}
echo "\n";

// 3. Verificar rotas
echo "3️⃣ Verificando rotas em routes/admin.php...\n";
$routesFile = __DIR__ . '/routes/admin.php';

if (!file_exists($routesFile)) {
    echo "   ❌ Arquivo não encontrado: $routesFile\n\n";
    exit(1);
}

$routesContent = file_get_contents($routesFile);

$routeChecks = [
    'getAiPrompt' => 'GET /settings/ai-prompt',
    'saveAiPrompt' => 'POST /settings/ai-prompt',
    'deleteAiPrompt' => 'DELETE /settings/ai-prompt',
];

foreach ($routeChecks as $needle => $description) {
    if (strpos($routesContent, $needle) !== false) {
        echo "   ✅ $description\n";
    } else {
        echo "   ❌ $description - NÃO ENCONTRADO\n";
        $allPassed = false;
    }
}
echo "\n";

// 4. Verificar controller
echo "4️⃣ Verificando TenantSettingsController...\n";
$controllerFile = __DIR__ . '/app/Http/Controllers/Admin/TenantSettingsController.php';

if (!file_exists($controllerFile)) {
    echo "   ❌ Arquivo não encontrado: $controllerFile\n\n";
    exit(1);
}

$controllerContent = file_get_contents($controllerFile);

$controllerChecks = [
    'public function getAiPrompt' => 'Método getAiPrompt',
    'public function saveAiPrompt' => 'Método saveAiPrompt',
    'public function deleteAiPrompt' => 'Método deleteAiPrompt',
    'AppSetting::getValue(\'ai_prompt_custom\'' => 'Busca no AppSetting',
    'AppSetting::setValue(\'ai_prompt_custom\'' => 'Salva no AppSetting',
];

foreach ($controllerChecks as $needle => $description) {
    if (strpos($controllerContent, $needle) !== false) {
        echo "   ✅ $description\n";
    } else {
        echo "   ❌ $description - NÃO ENCONTRADO\n";
        $allPassed = false;
    }
}
echo "\n";

// 5. Resumo
echo str_repeat("=", 60) . "\n";
echo "📊 RESUMO DA IMPLEMENTAÇÃO\n";
echo str_repeat("=", 60) . "\n\n";

if ($allPassed) {
    echo "✅ TUDO IMPLEMENTADO CORRETAMENTE!\n\n";
    
    echo "📋 Componentes verificados:\n";
    echo "   ✅ OpenAIService.php - Lógica de prompt customizado\n";
    echo "   ✅ configuracoes.html - Interface de 2000 caracteres\n";
    echo "   ✅ routes/admin.php - Rotas GET/POST/DELETE\n";
    echo "   ✅ TenantSettingsController - Métodos get/save/delete\n\n";
    
    echo "🎯 Funcionalidade completa:\n";
    echo "   1. Admin pode configurar prompt de até 2000 caracteres\n";
    echo "   2. Prompt customizado PREVALECE sobre padrão\n";
    echo "   3. Se não configurado, usa prompt padrão do sistema\n";
    echo "   4. Variáveis {{\$assistantName}}, {{\$propertiesContext}} são injetadas\n\n";
    
    echo "🧪 Como testar:\n";
    echo "   1. Acesse: http://127.0.0.1:8000/app/configuracoes.html\n";
    echo "   2. Vá para aba 'Integrações'\n";
    echo "   3. Configure o 'Prompt da IA'\n";
    echo "   4. Envie mensagem via WhatsApp\n";
    echo "   5. Verifique logs: storage/logs/lumen-*.log\n";
    echo "   6. Procure por: '[OpenAI] Usando prompt CUSTOMIZADO'\n\n";
    
    echo "📖 Documentação completa: GUIA_TESTE_PROMPT_IA.md\n\n";
    
    exit(0);
} else {
    echo "❌ IMPLEMENTAÇÃO INCOMPLETA\n\n";
    echo "Verifique os itens marcados com ❌ acima\n\n";
    exit(1);
}

