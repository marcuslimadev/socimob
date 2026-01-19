<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log do Sistema
 * Registra todas as operações importantes do sistema
 */
class SystemLog extends Model
{
    const UPDATED_AT = null; // Não usa updated_at
    
    protected $fillable = [
        'tenant_id',
        'user_id',
        'level',
        'category',
        'action',
        'message',
        'context',
        'ip_address',
        'user_agent',
        'stack_trace',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    // Níveis de log
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    // Categorias principais
    const CATEGORY_LEAD = 'lead';
    const CATEGORY_WHATSAPP = 'whatsapp';
    const CATEGORY_IA = 'ia';
    const CATEGORY_TWILIO = 'twilio';
    const CATEGORY_WEBHOOK = 'webhook';
    const CATEGORY_AUTH = 'auth';
    const CATEGORY_AUTOMATION = 'automation';
    const CATEGORY_INTEGRATION = 'integration';

    /**
     * Registrar log no banco de dados
     */
    public static function log(
        string $level,
        string $category,
        string $action,
        string $message,
        array $context = [],
        ?\Throwable $exception = null
    ) {
        try {
            $data = [
                'level' => $level,
                'category' => $category,
                'action' => $action,
                'message' => $message,
                'context' => $context,
                'ip_address' => request()->ip() ?? null,
                'user_agent' => request()->userAgent() ?? null,
            ];

            // Capturar tenant_id e user_id do request se disponível
            if (request()->user()) {
                $data['user_id'] = request()->user()->id;
                $data['tenant_id'] = request()->user()->tenant_id;
            }

            // Se houver exceção, adicionar stack trace
            if ($exception) {
                $data['stack_trace'] = $exception->getTraceAsString();
                $data['context']['exception'] = [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
            }

            self::create($data);
        } catch (\Exception $e) {
            // Se falhar ao salvar no banco, registra no arquivo
            \Log::error('Falha ao salvar SystemLog', [
                'error' => $e->getMessage(),
                'original_message' => $message,
            ]);
        }
    }

    // Métodos helpers para cada nível
    public static function debug(string $category, string $action, string $message, array $context = [])
    {
        self::log(self::LEVEL_DEBUG, $category, $action, $message, $context);
    }

    public static function info(string $category, string $action, string $message, array $context = [])
    {
        self::log(self::LEVEL_INFO, $category, $action, $message, $context);
    }

    public static function warning(string $category, string $action, string $message, array $context = [])
    {
        self::log(self::LEVEL_WARNING, $category, $action, $message, $context);
    }

    public static function error(string $category, string $action, string $message, array $context = [], ?\Throwable $exception = null)
    {
        self::log(self::LEVEL_ERROR, $category, $action, $message, $context, $exception);
    }

    public static function critical(string $category, string $action, string $message, array $context = [], ?\Throwable $exception = null)
    {
        self::log(self::LEVEL_CRITICAL, $category, $action, $message, $context, $exception);
    }

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
