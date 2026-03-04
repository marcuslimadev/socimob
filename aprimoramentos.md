# Aprimoramentos — ConversasController.php

> Análise de gargalos, falhas de segurança e funcionalidades incompletas/mocadas.  
> Arquivo: `app/Http/Controllers/ConversasController.php`

---

## 1. Gargalos de Performance

### 1.1 N+1 no `index()` — CRÍTICO
**Problema:** O `index()` faz eager loading de `mensagens` com `limit(1)`, mas logo em seguida chama **dois métodos de relacionamento adicionais dentro do `map()`**, gerando 2 queries extras por conversa:

```php
// PROBLEMA — para cada $conversa na coleção:
$conversa->user_messages_count = $conversa->mensagens()->where('direction', 'incoming')->count(); // 1 query
$conversa->total_messages       = $conversa->mensagens()->count();                                // 1 query
```

Para 50 conversas = **100 queries extras**. O eager loading carregado antes (`with(['mensagens' => ...]`) é ignorado aqui porque `->mensagens()` (com parênteses) reinicia uma nova query.

**Correção:**
```php
->withCount([
    'mensagens as user_messages_count' => fn($q) => $q->where('direction', 'incoming'),
    'mensagens as total_messages',
])
```

---

### 1.2 N+1 em `porTelefone()` — CRÍTICO
**Problema:** Dentro do `map()` sobre conversas, executa `$db->table('mensagens')->where('conversa_id', ...)` para **cada conversa** individualmente.

**Correção:** Buscar todos os IDs de conversas primeiro e fazer uma única query com `->whereIn('conversa_id', $ids)`, depois agrupar em PHP.

---

### 1.3 Full table scan em `porTelefone()` — ALTO
**Problema:** A busca com sufixo usa `LIKE '%XXXXXXXX'` (wildcard à esquerda), o que **impede uso de índice** e resulta em varredura completa da tabela:

```php
$query->orWhere('conversas.telefone', 'LIKE', '%' . $sufixo);
```

**Correção:** Criar coluna calculada `telefone_normalizado` (apenas dígitos) com índice, ou normalizar o telefone antes de armazenar.

---

### 1.4 `index()` sem paginação — ALTO
**Problema:** `->get()` retorna **todas** as conversas do tenant sem limite. Em tenants ativos, isso pode trazer milhares de registros de uma vez.

**Correção:** Usar `->paginate($request->input('per_page', 20))` e adaptar o frontend.

---

### 1.5 Dois `map()` consecutivos em `index()` — BAIXO
**Problema:** A coleção é percorrida duas vezes seguidas — uma para contadores, outra para formatar datas.

**Correção:** Unificar em um único `map()`.

---

### 1.6 `tempoReal()` sem cache — MÉDIO
**Problema:** Endpoint que presumivelmente é chamado por polling repetido, porém bate no banco toda vez sem nenhum cache.

**Correção:** Adicionar `Cache::remember("conversas_ativas_{$tenantId}", 5, fn() => ...)` com TTL de 5 segundos, ou migrar para WebSocket/SSE.

---

### 1.7 `show()` marca mensagens como lidas incondicionalmente — BAIXO
**Problema:** Executa `UPDATE` em mensagens toda vez que o `show()` é chamado, mesmo quando nada está não lido.

**Correção:** Verificar antes se existe alguma mensagem sem `read_at`:
```php
$hasPending = $db->table('mensagens')
    ->where('conversa_id', $id)
    ->where('direction', 'incoming')
    ->whereNull('read_at')
    ->exists();

if ($hasPending) { /* UPDATE */ }
```

---

## 2. Falhas de Segurança

### 2.1 Tenant check APÓS execução da query em `porTelefone()` — CRÍTICO
**Problema:** A validação `if (!$tenantId)` ocorre **depois** que a query foi executada. Se `$tenantId` for `null`, a query roda com `WHERE conversas.tenant_id = NULL`, o que retorna linhas onde `tenant_id IS NULL` — potencial vazamento de dados entre tenants.

```php
// ATUAL — bug de ordem:
$conversas = $db->table('conversas')
    ->where('conversas.tenant_id', $tenantId)  // null aqui = WHERE tenant_id IS NULL
    ->get();

if (!$tenantId) {  // já executou!
    return response()->json(['error' => 'Tenant não identificado'], 403);
}
```

**Correção:** Mover o guard para o início do método, antes de qualquer query.

---

### 2.2 Mesmo bug em `show()` — CRÍTICO
```php
if (!$conversa && !$tenantId) {  // condição AND permite que um dos dois seja verdadeiro
    return response()->json([...], 403);
}
```
A condição `&&` significa que se a conversa for encontrada mas `$tenantId` for nulo, o guard não é ativado. Deveria ser `||` ou, melhor, um guard antecipado.

---

### 2.3 Validação da URL no `proxyMedia()` insuficiente — ALTO
**Problema:** A validação verifica apenas `str_contains($mediaUrl, 'twilio.com')`. Uma URL como `https://evil.com/attack?ref=api.twilio.com` passaria na validação.

**Correção:**
```php
$parsed = parse_url($mediaUrl);
$allowedHosts = ['api.twilio.com', 'media.twiliocdn.com'];
if (!in_array($parsed['host'] ?? '', $allowedHosts, true)) {
    return response()->json(['error' => 'URL inválida'], 403);
}
```

---

### 2.4 Stack trace e caminhos de arquivo expostos — MÉDIO
**Problema:** Em `porTelefone()`, a resposta de erro **sempre** retorna `file` e `line` independentemente do ambiente:

```php
return response()->json([
    'error'  => $e->getMessage(),
    'file'   => $e->getFile(),   // expõe estrutura de diretórios do servidor
    'line'   => $e->getLine()
], 500);
```

**Correção:** Condicionar ao `config('app.debug')`, igual ao `show()`.

---

### 2.5 `sendMessage()` sem limite de tamanho no conteúdo — MÉDIO
**Problema:** A validação só exige `'content' => 'required|string'`. Payloads de megabytes são aceitos e enviados ao Twilio.

**Correção:** `'content' => 'required|string|max:4096'`

---

### 2.6 `destroy()` e `bulkDestroy()` sem restrição de papel — MÉDIO
**Problema:** Qualquer usuário autenticado (inclusive corretores comuns) pode deletar conversas e mensagens permanentemente. O método `assign()` corretamente verifica `admin`, mas os de exclusão não verificam.

**Correção:** Adicionar verificação de papel em ambos:
```php
if (!in_array($request->user()?->role, ['admin', 'super_admin'])) {
    return response()->json(['error' => 'Acesso negado'], 403);
}
```

---

### 2.7 `proxyMedia()` sem rate limiting ou limite de tamanho — MÉDIO
**Problema:** Qualquer usuário autenticado pode usar o servidor como proxy ilimitado para baixar arquivos de mídia do Twilio (potencial abuso de banda).

**Correção:** Aplicar middleware de rate limiting na rota e adicionar verificação de `Content-Length` antes de retornar o stream.

---

### 2.8 `resolveTenantId()` com fallback implícito — BAIXO
**Problema:** O terceiro fallback do método usa `app('tenant')->id` sem nenhuma verificação de contexto. Em cenários de fila ou CLI, `app('tenant')` pode ter sido resolvido de uma requisição anterior, causando vazamento cross-tenant.

---

## 3. Funcionalidades Mocadas / Não Desenvolvidas

### 3.1 `tempoReal()` — Polling disfarçado de "Tempo Real"
**Problema:** O método se chama `tempoReal` e está em uma rota supostamente de atualização em tempo real, mas é apenas um endpoint HTTP comum que retorna os dados no momento da chamada. Não há WebSocket, Server-Sent Events (SSE) nem push. O "tempo real" real depende de polling no frontend.

**O que falta implementar:**
- Laravel Broadcasting com `Pusher` ou `Reverb` (nativo Laravel 11)
- Canal `ConversaAtualizada` com evento `MessageReceived`
- Frontend ouvindo `Echo.channel('conversas').listen(...)`

---

### 3.2 `sendMessage()` para canal portal — sem notificação em tempo real
**Problema:** Mensagens enviadas para conversas do canal `portal:` são salvas no banco, mas **nenhum evento é disparado**. O cliente portal não tem como saber que chegou uma nova mensagem sem recarregar a página.

```php
// Salva no banco e retorna — cliente fica no escuro
$mensagem = Mensagem::create([...]);
// Falta: event(new NovaMensagem($mensagem));
```

---

### 3.3 `assign()` — sem notificação ao corretor designado
**Problema:** Quando uma conversa é redesignada para um corretor, o corretor não recebe nenhuma notificação (push, e-mail, evento de broadcast).

**O que falta:** Disparar `Notification::send($corretor, new ConversaAtribuida($conversa))`.

---

### 3.4 Read receipts não são enviados de volta ao WhatsApp
**Problema:** O `show()` marca mensagens como lidas no banco (`read_at`), mas **não envia o "visto" (read receipt) ao WhatsApp via Twilio**. Do ponto de vista do cliente, a mensagem nunca foi lida.

**O que falta:** Chamar `$this->twilio->markAsRead($messageSid)` após o update (se a API Twilio suportar para o número configurado).

---

### 3.5 `deleteConversas()` — sem soft delete / auditoria
**Problema:** Exclusão permanente e imediata de conversas, mensagens e documentos sem:
- Soft delete (coluna `deleted_at`)
- Log de auditoria (quem deletou, quando, tenant)
- Possibilidade de recuperação

**O que falta:** Implementar `SoftDeletes` nos models `Conversa` e `Mensagem`, e registrar a ação em uma tabela de auditoria.

---

### 3.6 `index()` sem filtro por corretor para não-admins
**Problema:** Corretores comuns podem ver **todas** as conversas do tenant, incluindo as atribuídas a outros corretores. Falta filtrar automaticamente por `corretor_id` quando o usuário não é admin.

---

### 3.7 Busca/filtro em `index()` limitado
**Problema:** O `index()` suporta apenas filtro por `status`. Não há:
- Busca por nome/telefone do lead
- Filtro por corretor
- Filtro por canal (whatsapp, portal)
- Filtro por data
- Filtro por não lidas

---

## 4. Inconsistências de Código

| Ponto | Problema |
|---|---|
| `index()` usa Eloquent | `show()` usa `app('db')->table(...)` — abordagem mista sem motivo técnico |
| `show()` passa `request()` global | Deveria receber `Request $request` via injeção, como os outros métodos |
| `porTelefone()` é rota de debug | Exposta sem proteção de ambiente (`APP_ENV=production` deveria bloqueá-la) |
| Datas formatadas manualmente com `date('c', strtotime(...))` | Eloquent já retorna `Carbon` — usar `$conversa->ultima_atividade->toIso8601String()` |
| `users.nome` no join de `porTelefone()` | A coluna é `users.name` (inglês) — retorna null silenciosamente |

---

## 5. Resumo de Prioridades

| Prioridade | Item | Impacto |
|---|---|---|
| 🔴 Crítico | N+1 no `index()` (contadores) | Performance — 2× queries por conversa |
| 🔴 Crítico | Tenant check após query em `porTelefone()` | Segurança — vazamento cross-tenant |
| 🔴 Crítico | Guard `&&` em `show()` (deveria ser `\|\|`) | Segurança — bypass de autorização |
| 🟠 Alto | N+1 em `porTelefone()` | Performance |
| 🟠 Alto | Full table scan com `LIKE '%sufixo'` | Performance — sem índice |
| 🟠 Alto | `proxyMedia()` validação de URL fraca | Segurança — SSRF |
| 🟠 Alto | `index()` sem paginação | Performance — OOM em tenants grandes |
| 🟡 Médio | Stack trace exposto em produção | Segurança — information disclosure |
| 🟡 Médio | Delete sem restrição de papel | Segurança |
| 🟡 Médio | `sendMessage()` sem limite de tamanho | Segurança |
| 🔵 Baixo | `tempoReal()` sem WebSocket real | Funcional — UX degradada |
| 🔵 Baixo | Notificação ao corretor no `assign()` | Funcional |
| 🔵 Baixo | Read receipts WhatsApp | Funcional |
| 🔵 Baixo | Soft delete com auditoria | Operacional |
