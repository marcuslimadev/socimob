<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller para deploy automático via webhook
 */
class DeployController extends Controller
{
    /**
     * Secret token para validar requisições
     * Configure no .env: DEPLOY_SECRET=seu-token-secreto
     */
    private function validateSecret(Request $request): bool
    {
        $secret = $request->header('X-Deploy-Secret') ?? $request->input('secret');
        $expectedSecret = env('DEPLOY_SECRET', 'change-me-in-production');
        
        return hash_equals($expectedSecret, $secret);
    }

    /**
     * Endpoint de deploy
     * POST /api/deploy
     * 
     * Headers:
     *   X-Deploy-Secret: seu-token-secreto
     * 
     * Body (opcional):
     *   {
     *     "project": "lojadaesquina" // ou "exclusiva"
     *   }
     */
    public function deploy(Request $request)
    {
        Log::info('═══════════════════════════════════════════════');
        Log::info('🚀 DEPLOY WEBHOOK RECEBIDO');
        Log::info('═══════════════════════════════════════════════');
        
        // Validar secret token
        if (!$this->validateSecret($request)) {
            Log::warning('❌ Deploy rejeitado: Token inválido', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid deploy secret'
            ], 401);
        }

        $project = $request->input('project', 'exclusiva');
        $output = [];
        $errors = [];
        $startTime = microtime(true);

        Log::info("📦 Projeto: $project");

        try {
            // Definir diretório base conforme o projeto
            $baseDir = $this->getProjectPath($project);
            
            if (!$baseDir) {
                throw new \Exception("Projeto '$project' não configurado");
            }

            Log::info("📁 Diretório: $baseDir");

            // ==========================================
            // 1. GIT PULL
            // ==========================================
            Log::info('🔄 Executando git pull...');
            
            $gitCommand = "cd $baseDir && git pull 2>&1";
            exec($gitCommand, $gitOutput, $gitReturnCode);
            
            $output['git_pull'] = [
                'command' => $gitCommand,
                'output' => $gitOutput,
                'exit_code' => $gitReturnCode
            ];

            if ($gitReturnCode !== 0) {
                $errors[] = 'Git pull falhou';
                Log::error('❌ Git pull falhou', $output['git_pull']);
            } else {
                Log::info('✅ Git pull concluído', ['output' => implode("\n", $gitOutput)]);
            }

            // ==========================================
            // 2. COMPOSER INSTALL
            // ==========================================
            Log::info('📦 Executando composer install...');
            
            // Detectar path do PHP (cPanel usa /opt/alt/phpXX)
            $phpPath = $this->getPhpPath();
            $composerPath = $this->getComposerPath();
            
            // Definir variáveis de ambiente necessárias para o Composer
            $homeDir = $baseDir; // Usar diretório do projeto como HOME
            $composerCommand = "cd $baseDir && HOME=$homeDir COMPOSER_HOME=$homeDir/.composer $phpPath $composerPath install --no-dev --optimize-autoloader 2>&1";
            exec($composerCommand, $composerOutput, $composerReturnCode);
            
            $output['composer_install'] = [
                'command' => $composerCommand,
                'output' => $composerOutput,
                'exit_code' => $composerReturnCode
            ];

            if ($composerReturnCode !== 0) {
                $errors[] = 'Composer install falhou';
                Log::error('❌ Composer install falhou', $output['composer_install']);
            } else {
                Log::info('✅ Composer install concluído', ['output' => implode("\n", $composerOutput)]);
            }

            // ==========================================
            // 3. SVELTE BUILD
            // ==========================================
            Log::info('🎨 Build do Portal Svelte...');
            
            $svelteDir = $baseDir . '/portal-svelte';
            
            if (is_dir($svelteDir)) {
                try {
                    $npmPath = $this->findCommand('npm');
                    $nodePath = $this->findCommand('node');
                    
                    if ($npmPath && $nodePath) {
                        // Configura PATH completo com node, npm e node_modules/.bin
                        $nodeBinDir = dirname($nodePath);
                        $npmBinDir = dirname($npmPath);
                        $pathDirs = array_unique([
                            $nodeBinDir, 
                            $npmBinDir, 
                            "$svelteDir/node_modules/.bin",
                            '/bin',           // Para sh, bash, etc
                            '/usr/bin',
                            '/usr/local/bin'
                        ]);
                        $pathEnv = implode(':', $pathDirs);
                        
                        $env = "HOME=$homeDir NODE_ENV=production PATH=$pathEnv";
                        
                        Log::info("📍 Node path: $nodePath");
                        Log::info("📍 npm path: $npmPath");
                        
                        // npm install (INCLUIR devDependencies - vite precisa delas!)
                        Log::info('📦 npm install...');
                        $envInstall = "HOME=$homeDir PATH=$pathEnv NPM_CONFIG_PRODUCTION=false"; // força instalar devDependencies
                        exec("cd $svelteDir && $envInstall $npmPath install --include=dev 2>&1", $npmInstallOutput, $npmInstallCode);
                        
                        // Build usando vite diretamente (mais confiável que npm run build)
                        Log::info('🔨 vite build...');
                        $vitePath = "$svelteDir/node_modules/.bin/vite";
                        $viteJsPath = "$svelteDir/node_modules/vite/bin/vite.js"; // fallback direto no bin JS

                        // Detecta binário nativo do esbuild (evita fallback wasm/OOM)
                        $esbuildBinary = null;
                        $esbuildCandidates = [
                            "$svelteDir/node_modules/@esbuild/linux-x64/bin/esbuild",
                            "$svelteDir/node_modules/esbuild-linux-64/bin/esbuild",
                            "$svelteDir/node_modules/esbuild/bin/esbuild",
                        ];
                        foreach ($esbuildCandidates as $candidate) {
                            if (file_exists($candidate) && is_file($candidate)) {
                                $esbuildBinary = $candidate;
                                break;
                            }
                        }

                        // Limita memória e inclui ESBUILD_BINARY_PATH se encontrado
                        $envBuild = "HOME=$homeDir NODE_ENV=production NODE_OPTIONS=--max-old-space-size=512 PATH=$pathEnv"; // Usar production só no build
                        if ($esbuildBinary) {
                            $envBuild .= " ESBUILD_BINARY_PATH=$esbuildBinary";
                            Log::info('📍 esbuild bin: ' . $esbuildBinary);
                        } else {
                            Log::warning('⚠️ esbuild bin não encontrado, pode cair em wasm');
                        }
                        if (file_exists($vitePath)) {
                            exec("cd $svelteDir && $envBuild $nodePath $vitePath build 2>&1", $npmBuildOutput, $npmBuildCode);
                        } elseif (file_exists($viteJsPath)) {
                            Log::info('📍 usando vite.js direto (sem .bin)');
                            exec("cd $svelteDir && $envBuild $nodePath $viteJsPath build 2>&1", $npmBuildOutput, $npmBuildCode);
                        } else {
                            // Fallback para npm run build
                            exec("cd $svelteDir && $envBuild $npmPath run build 2>&1", $npmBuildOutput, $npmBuildCode);
                        }
                        
                        $output['svelte_build'] = [
                            'available' => true,
                            'npm_install' => $npmInstallOutput ?? [],
                            'npm_install_code' => $npmInstallCode ?? 1,
                            'npm_build' => $npmBuildOutput ?? [],
                            'npm_build_code' => $npmBuildCode ?? 1,
                            'exit_code' => $npmBuildCode ?? 1
                        ];
                        
                        if ($npmBuildCode === 0) {
                            Log::info('✅ Svelte build concluído');
                        } else {
                            $errors[] = 'Svelte build falhou';
                            Log::error('❌ Svelte build falhou', $output['svelte_build']);
                        }
                    } else {
                        $output['svelte_build'] = [
                            'available' => false,
                            'message' => ($nodePath ? '' : 'node não encontrado; ') . 
                                       ($npmPath ? '' : 'npm não encontrado') . 
                                       ' (Svelte build ignorado)'
                        ];
                        Log::warning('⚠️ npm ou node não encontrado, Svelte build ignorado');
                    }
                } catch (\Exception $e) {
                    $output['svelte_build'] = [
                        'available' => false,
                        'error' => $e->getMessage()
                    ];
                    Log::error('❌ Erro no Svelte build: ' . $e->getMessage());
                }
            } else {
                $output['svelte_build'] = [
                    'available' => false,
                    'message' => 'Portal Svelte não encontrado'
                ];
                Log::info('ℹ️ Portal Svelte não encontrado em ' . $svelteDir);
            }

            // ==========================================
            // 4. CACHE CLEAR (Lumen)
            // ==========================================
            Log::info('🧹 Limpando cache...');
            
            $cacheCommands = [
                "cd $baseDir && rm -rf bootstrap/cache/*.php",
                "cd $baseDir && rm -rf storage/framework/cache/*",
                "cd $baseDir && rm -rf storage/framework/views/*"
            ];

            foreach ($cacheCommands as $cmd) {
                exec($cmd . ' 2>&1', $cacheOutput, $cacheReturnCode);
            }

            $output['cache_clear'] = [
                'commands' => $cacheCommands,
                'output' => $cacheOutput ?? [],
                'exit_code' => $cacheReturnCode ?? 0
            ];

            Log::info('✅ Cache limpo');

            // ==========================================
            // 3.5. ARTISAN COMMANDS (se existir)
            // ==========================================
            if (file_exists("$baseDir/artisan")) {
                Log::info('🔧 Executando comandos artisan...');
                
                $artisanCommands = [
                    "cd $baseDir && $phpPath artisan route:clear",
                    "cd $baseDir && $phpPath artisan cache:clear",
                    "cd $baseDir && $phpPath artisan config:clear"
                ];

                foreach ($artisanCommands as $cmd) {
                    exec($cmd . ' 2>&1', $artisanOutput, $artisanReturnCode);
                }

                $output['artisan_commands'] = [
                    'commands' => $artisanCommands,
                    'output' => $artisanOutput ?? [],
                    'exit_code' => $artisanReturnCode ?? 0
                ];

                Log::info('✅ Comandos artisan executados');
            }

            // ==========================================
            // 4. PERMISSÕES (opcional, pode causar erro em alguns hosts)
            // ==========================================
            Log::info('🔐 Ajustando permissões...');
            
            $permCommands = [
                "cd $baseDir && chmod -R 775 storage",
                "cd $baseDir && chmod -R 775 bootstrap/cache"
            ];

            foreach ($permCommands as $cmd) {
                exec($cmd . ' 2>&1', $permOutput, $permReturnCode);
            }

            $output['permissions'] = [
                'commands' => $permCommands,
                'output' => $permOutput ?? [],
                'exit_code' => $permReturnCode ?? 0
            ];

            Log::info('✅ Permissões ajustadas');

            // ==========================================
            // RESULTADO FINAL
            // ==========================================
            $duration = round(microtime(true) - $startTime, 2);
            $success = empty($errors);

            Log::info('═══════════════════════════════════════════════');
            Log::info($success ? '✅ DEPLOY CONCLUÍDO COM SUCESSO' : '⚠️ DEPLOY CONCLUÍDO COM ERROS');
            Log::info("⏱️  Tempo: {$duration}s");
            Log::info('═══════════════════════════════════════════════');

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Deploy realizado com sucesso' : 'Deploy concluído com erros',
                'project' => $project,
                'duration' => $duration . 's',
                'errors' => $errors,
                'output' => $output,
                'timestamp' => date('Y-m-d H:i:s')
            ], $success ? 200 : 207); // 207 = Multi-Status (parcialmente bem-sucedido)

        } catch (\Exception $e) {
            Log::error('❌ ERRO NO DEPLOY', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro no deploy: ' . $e->getMessage(),
                'project' => $project,
                'output' => $output,
                'timestamp' => date('Y-m-d H:i:s')
            ], 500);
        }
    }

    /**
     * Obter path do projeto
     */
    private function getProjectPath(string $project): ?string
    {
        $paths = [
            'lojadaesquina' => env('DEPLOY_PATH_LOJA', '/home/usuario/domains/lojadaesquina.store/public_html'),
            'exclusiva' => env('DEPLOY_PATH_EXCLUSIVA', '/home/usuario/domains/exclusivalarimoveis.com/public_html'),
            'default' => env('DEPLOY_PATH_DEFAULT', base_path())
        ];

        return $paths[$project] ?? $paths['default'];
    }

    /**
     * Obter path do PHP (cPanel geralmente usa /opt/alt/phpXX)
     */
    private function getPhpPath(): string
    {
        // Tentar detectar automaticamente
        $phpPaths = [
            '/opt/alt/php83/usr/bin/php',  // cPanel PHP 8.3
            '/opt/alt/php82/usr/bin/php',  // cPanel PHP 8.2
            '/opt/alt/php81/usr/bin/php',  // cPanel PHP 8.1
            '/usr/bin/php',                // Padrão Linux
            'php'                          // Fallback
        ];

        foreach ($phpPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return env('PHP_PATH', 'php');
    }

    /**
     * Obter path do Composer
     */
    private function getComposerPath(): string
    {
        $composerPaths = [
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            'composer'
        ];

        foreach ($composerPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return env('COMPOSER_PATH', 'composer');
    }

    /**
     * Encontrar comando no sistema
     */
    private function findCommand($command)
    {
        // Caminhos específicos para Node.js/npm no CloudLinux
        if ($command === 'node') {
            $nodePaths = [
                '/opt/alt/alt-nodejs20/root/usr/bin/node',  // Preferir Node 20
                '/opt/alt/alt-nodejs18/root/usr/bin/node',
                '/opt/alt/alt-nodejs22/root/usr/bin/node',
                '/opt/alt/alt-nodejs24/root/usr/bin/node',
                '/usr/local/bin/node',
                '/usr/bin/node',
            ];
            
            foreach ($nodePaths as $path) {
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
            return null;
        }
        
        if ($command === 'npm') {
            $npmPaths = [
                '/opt/alt/alt-nodejs20/root/usr/bin/npm',
                '/opt/alt/alt-nodejs18/root/usr/bin/npm',
                '/opt/alt/alt-nodejs22/root/usr/bin/npm',
                '/opt/alt/alt-nodejs24/root/usr/bin/npm',
                '/usr/local/bin/npm',
                '/usr/bin/npm',
            ];
            
            foreach ($npmPaths as $path) {
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
            return null;
        }
        
        // Fallback genérico
        $paths = [
            "/usr/local/bin/$command",
            "/usr/bin/$command",
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }
        
        // Tentar via which
        $result = shell_exec("which $command 2>/dev/null");
        if ($result && file_exists(trim($result))) {
            return trim($result);
        }

        return null;
    }

    /**
     * Informações do sistema (útil para debug)
     * GET /api/deploy/info
     */
    public function info(Request $request)
    {
        if (!$this->validateSecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'php_version' => PHP_VERSION,
            'php_path' => $this->getPhpPath(),
            'composer_path' => $this->getComposerPath(),
            'base_path' => base_path(),
            'server' => [
                'os' => PHP_OS,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'user' => get_current_user()
            ],
            'git' => [
                'available' => shell_exec('which git') !== null,
                'version' => trim(shell_exec('git --version') ?? 'N/A')
            ]
        ]);
    }
}
