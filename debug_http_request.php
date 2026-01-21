<?php
require_once 'router.php';

// Simular uma requisição HTTP para /api/portal/imoveis
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/portal/imoveis';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8000';

// Headers de autenticação (simulando um usuário logado)
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJ1c2VyX2lkIjoxLCJ0aW1lc3RhbXAiOjE3MzQ2MzQ4MDAsInNlY3JldCI6InRlc3Rfc2VjcmV0In0=';

// Simular o contexto do Lumen
$app = require_once 'bootstrap/app.php';

// Executar a aplicação
$app->run();