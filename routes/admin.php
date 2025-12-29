<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Admin Routes (Tenant Admin)
$router->group(['prefix' => 'api/admin', 'middleware' => ['simple-auth']], function () use ($router) {
    
    // Settings
    $router->get('/settings', 'Admin\TenantSettingsController@index');
    $router->put('/settings/tenant', 'Admin\TenantSettingsController@updateTenant');
    $router->put('/settings/theme', 'Admin\TenantSettingsController@updateTheme');
    $router->put('/settings/domain', 'Admin\TenantSettingsController@updateDomain');
    $router->post('/settings/assets', 'Admin\TenantSettingsController@uploadAssets');

    // Email Settings
    $router->get('/settings/email', 'Admin\TenantSettingsController@getEmailSettings');
    $router->put('/settings/email', 'Admin\TenantSettingsController@updateEmailSettings');

    // Notification Settings
    $router->get('/settings/notifications', 'Admin\TenantSettingsController@getNotificationSettings');
    $router->put('/settings/notifications', 'Admin\TenantSettingsController@updateNotificationSettings');

    // Importação de Imóveis
    $router->get('/imoveis', 'Admin\ImportacaoController@listar');
    $router->post('/imoveis/importar', 'Admin\ImportacaoController@importar');
    $router->get('/imoveis/importar/{jobId}', 'Admin\ImportacaoController@status');
    $router->post('/importacao/teste-api', 'Admin\ImportacaoController@testarAPI');

    // Visitas
    $router->get('/visitas', 'Admin\\VisitasController@index');
    $router->patch('/visitas/{id}', 'Admin\\VisitasController@update');

    // Usuários/Equipe
    $router->get('/users', function () use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $users = app('db')->table('users')
            ->where('tenant_id', $user->tenant_id)
            ->select('id', 'name', 'email', 'role', 'is_active as ativo', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['data' => $users]);
    });
    
    $router->post('/users', function () use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $data = app('request')->all();
        
        // Validação básica
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
            return response()->json(['message' => 'Campos obrigatórios faltando'], 400);
        }
        
        // Verifica se email já existe no tenant
        $exists = app('db')->table('users')
            ->where('tenant_id', $user->tenant_id)
            ->where('email', $data['email'])
            ->exists();
            
        if ($exists) {
            return response()->json(['message' => 'Email já cadastrado'], 400);
        }
        
        // Criar usuário
        $userId = app('db')->table('users')->insertGetId([
            'tenant_id' => $user->tenant_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role' => $data['role'] ?? 'user',
            'is_active' => $data['ativo'] ?? true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return response()->json(['message' => 'Usuário criado com sucesso', 'id' => $userId], 201);
    });
    
    $router->put('/users/{id}', function ($id) use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $data = app('request')->all();
        
        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? 'user',
            'is_active' => $data['ativo'] ?? true,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Atualizar senha apenas se fornecida
        if (!empty($data['password'])) {
            $update['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        app('db')->table('users')
            ->where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->update($update);
            
        return response()->json(['message' => 'Usuário atualizado com sucesso']);
    });
    
    $router->delete('/users/{id}', function ($id) use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        app('db')->table('users')
            ->where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->delete();
            
        return response()->json(['message' => 'Usuário excluído com sucesso']);
    });

    // Portal chat
    $router->post('/portal-chat/{id}/take', 'Admin\\PortalChatController@take');
    $router->post('/portal-chat/{id}/release', 'Admin\\PortalChatController@release');

    // Chaves na Mão - Integração de Leads
    $router->get('/chaves-na-mao/status', 'ChavesNaMaoController@status');
    $router->post('/chaves-na-mao/test', 'ChavesNaMaoController@test');
    $router->post('/chaves-na-mao/retry', 'ChavesNaMaoController@retry');
    $router->post('/chaves-na-mao/resend', 'ChavesNaMaoController@resend');
});
