<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    /**
     * Cache dos nomes de colunas para compatibilidade com diferentes bancos.
     *
     * @var array<string, string>|null
     */
    protected static ?array $columnMap = null;

    protected $fillable = [
        'chave',
        'valor',
        'key',
        'value',
        'tipo',
        'descricao',
        'categoria',
        'editavel',
    ];

    /**
     * Resolve o nome físico das colunas de chave e valor.
     *
     * Isso permite compatibilidade com bancos que já possuíam as colunas em
     * inglês (key/value) antes da padronização para português.
     */
    protected static function resolveColumns(): ?array
    {
        if (!Schema::hasTable('app_settings')) {
            return null;
        }

        if (static::$columnMap !== null) {
            return static::$columnMap;
        }

        $keyColumn = Schema::hasColumn('app_settings', 'chave')
            ? 'chave'
            : (Schema::hasColumn('app_settings', 'key') ? 'key' : null);

        $valueColumn = Schema::hasColumn('app_settings', 'valor')
            ? 'valor'
            : (Schema::hasColumn('app_settings', 'value') ? 'value' : null);

        if (!$keyColumn || !$valueColumn) {
            throw new \RuntimeException('Colunas chave/valor de app_settings não encontradas.');
        }

        static::$columnMap = [
            'key' => $keyColumn,
            'value' => $valueColumn,
        ];

        return static::$columnMap;
    }

    /**
     * Retorna o valor da configuração ou o padrão informado
     */
    public static function getValue(string $key, $default = null)
    {
        $columns = static::resolveColumns();

        if (!$columns) {
            return $default;
        }

        $setting = static::where($columns['key'], $key)->first();

        if (!$setting) {
            return $default;
        }

        $value = $setting->{$columns['value']};

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_scalar($decoded))) {
            return $decoded;
        }

        return $value ?? $default;
    }

    /**
     * Atualiza ou cria uma configuração
     */
    public static function setValue(string $key, $value, ?string $tipo = 'string', ?string $descricao = null, ?string $categoria = null): self
    {
        $columns = static::resolveColumns();

        if (!$columns) {
            throw new \RuntimeException('Tabela app_settings não encontrada. Execute as migrações.');
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return static::updateOrCreate(
            [$columns['key'] => $key],
            [
                $columns['value'] => $value,
                'tipo' => $tipo,
                'descricao' => $descricao,
                'categoria' => $categoria,
            ]
        );
    }

    /**
     * Retorna todas as configurações no formato chave => valor respeitando o mapeamento.
     */
    public static function pluckSettings(): array
    {
        $columns = static::resolveColumns();

        if (!$columns) {
            return [];
        }

        return static::query()
            ->get()
            ->mapWithKeys(function (self $setting) use ($columns) {
                return [
                    $setting->{$columns['key']} => $setting->{$columns['value']},
                ];
            })
            ->toArray();
    }
}
