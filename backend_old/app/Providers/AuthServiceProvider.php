<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        $this->garantirColunaApiToken();

        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('api', function ($request) {
            $token = $request->input('api_token') ?? $request->bearerToken();

            if (!$token) {
                return null;
            }

            return $this->buscarUsuarioPorToken($token);
        });
    }

    private function buscarUsuarioPorToken(string $token): ?User
    {
        try {
            return User::where('api_token', $token)->first();
        } catch (QueryException $e) {
            if ($this->isMissingApiTokenColumn($e)) {
                $this->garantirColunaApiToken(true);

                return User::where('api_token', $token)->first();
            }

            throw $e;
        }
    }

    private function garantirColunaApiToken(bool $force = false): void
    {
        try {
            if (!Schema::hasTable('users')) {
                return;
            }

            if (Schema::hasColumn('users', 'api_token') && !$force) {
                $this->garantirIndiceUnicoApiToken();

                return;
            }

            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'api_token')) {
                    $table->string('api_token', 255)->nullable();
                }
            });

            $this->garantirIndiceUnicoApiToken();
        } catch (\Throwable $e) {
            Log::warning('Não foi possível garantir a coluna api_token na tabela users.', [
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function garantirIndiceUnicoApiToken(): void
    {
        try {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_api_token_unique ON users (api_token)');
        } catch (\Throwable $e) {
            Log::warning('Não foi possível garantir o índice único para api_token.', [
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function isMissingApiTokenColumn(QueryException $exception): bool
    {
        if ($exception->getCode() === '42703') {
            return true;
        }

        return Str::contains(Str::lower($exception->getMessage()), 'column "api_token" does not exist');
    }
}
