# Stages do Funil de Atendimento WhatsApp

## 📊 Fluxo Inteligente de Conversação

### 1. **boas_vindas** (Inicial)
- **Gatilho**: Primeira mensagem do cliente
- **Ação**: Sistema envia mensagem de boas-vindas calorosa
- **Objetivo**: Criar conexão emocional e apresentar a imobiliária
- **Próximo**: → coleta_dados

### 2. **coleta_dados** (Qualificação Inicial)
- **Gatilho**: Cliente responde à mensagem de boas-vindas
- **Ação**: IA extrai informações (orçamento, localização, quartos, desejos)
- **Dados capturados**:
  - Nome (já vem do ProfileName do WhatsApp)
  - Orçamento (budget_min, budget_max)
  - Localização desejada
  - Número de quartos/suítes
  - Características especiais
- **Validação**: Tem pelo menos orçamento OU localização OU quartos?
- **Próximo**: → matching (se tem dados suficientes) OU → aguardando_info

### 3. **aguardando_info** (Precisa de mais dados)
- **Gatilho**: Dados insuficientes para matching
- **Ação**: IA faz perguntas direcionadas para completar perfil
- **Estratégia**: Perguntar de forma natural, sem parecer formulário
- **Próximo**: → coleta_dados (continua coletando) → matching

### 4. **matching** (Busca Automática)
- **Gatilho**: Tem budget + localização + quartos
- **Ação**: Sistema busca imóveis compatíveis no banco
- **Critérios**:
  - Preço dentro do orçamento (±10% tolerância)
  - Localização próxima
  - Quartos >= solicitado
  - Score de matching calculado
- **Resultado**:
  - Se encontrou imóveis: → apresentacao
  - Se não encontrou: → sem_match

### 5. **apresentacao** (Mostrando Imóveis)
- **Gatilho**: Imóveis encontrados (1-5 opções)
- **Ação**: Envia detalhes dos imóveis com fotos
- **Formato**:
  ```
  🏡 Encontrei X imóveis perfeitos para você!
  
  📍 [Nome do Imóvel]
  💰 R$ XXX.XXX
  📐 XX m² | X quartos | X vagas
  ⭐ [Destaques principais]
  🔗 [Link com fotos]
  ```
- **Próximo**: → interesse (cliente demonstra interesse) OU → refinamento

### 6. **interesse** (Cliente Engajado)
- **Gatilho**: Cliente pergunta sobre um imóvel específico
- **Ação**: Aprofunda informações, envia mais fotos/vídeos
- **Objetivo**: Agendar visita
- **Próximo**: → agendamento OU → negociacao

### 7. **refinamento** (Ajustando Busca)
- **Gatilho**: Cliente não gostou das opções
- **Ação**: IA pergunta "O que não te agradou?" e ajusta critérios
- **Estratégia**: Aprender preferências e fazer nova busca
- **Próximo**: → matching (nova busca) → apresentacao

### 8. **sem_match** (Nenhum Imóvel Encontrado)
- **Gatilho**: Busca não retornou resultados
- **Ação**: 
  - Explicar que não tem disponível no momento
  - Oferecer ajustar critérios (orçamento, localização, quartos)
  - Oferecer cadastro para avisar quando chegar algo
- **Próximo**: → refinamento OU → aguardando_novidade

### 9. **agendamento** (Marcando Visita)
- **Gatilho**: Cliente quer visitar imóvel
- **Ação**: 
  - Coletar disponibilidade (dia/hora)
  - Confirmar endereço do imóvel
  - Gerar compromisso para corretor
- **Status Lead**: 'qualificado'
- **Próximo**: → visita_agendada

### 10. **visita_agendada** (Compromisso Confirmado)
- **Gatilho**: Data e hora confirmadas
- **Ação**:
  - Enviar confirmação com detalhes
  - Lembrete 1 dia antes
  - Lembrete 2h antes
- **Atribuição**: Corretor designado
- **Próximo**: → pos_visita

### 11. **pos_visita** (Feedback da Visita)
- **Gatilho**: Após data da visita (envio automático após 2h)
- **Ação**: Perguntar "E aí, gostou do imóvel?"
- **Respostas possíveis**:
  - Gostou: → negociacao
  - Não gostou: → refinamento
  - Sem resposta: → follow_up

### 12. **negociacao** (Fechando Negócio)
- **Gatilho**: Cliente demonstra intenção de compra/aluguel
- **Ação**: 
  - Corretor assume conversa (ou IA auxilia)
  - Negocia valores, condições
  - Envia proposta formal
- **Status Lead**: 'proposta'
- **Próximo**: → fechamento OU → perdido

### 13. **fechamento** (Deal Fechado! 🎉)
- **Gatilho**: Proposta aceita / Contrato assinado
- **Ação**: 
  - Enviar mensagem de parabéns
  - Instruções próximos passos
  - Solicitar avaliação/indicação
- **Status Lead**: 'fechado'
- **Fim do funil**: ✅ Sucesso

### 14. **perdido** (Não Fechou)
- **Gatilho**: 
  - Cliente desistiu explicitamente
  - 7 dias sem resposta após proposta
  - Fechou com concorrente
- **Ação**: 
  - Agradecer pelo contato
  - Manter no CRM para remarketing futuro
- **Status Lead**: 'perdido'
- **Fim do funil**: ❌ Não converteu

### 15. **follow_up** (Reengajamento)
- **Gatilho**: 
  - 3 dias sem resposta em qualquer stage
  - Cliente "esfriou" após interesse
- **Ação**: 
  - Mensagem leve de reengajamento
  - "Tem alguma dúvida que eu posso ajudar?"
  - Novidades de imóveis similares
- **Próximo**: Retorna ao stage anterior OU → inativo

### 16. **inativo** (Cliente Sumiu)
- **Gatilho**: 14 dias sem resposta
- **Ação**: Pausar automação ativa
- **Estratégia**: Remarketing mensal com novidades
- **Possível retorno**: qualquer stage anterior

### 17. **aguardando_corretor** (Transferência Humana)
- **Gatilho**: 
  - Cliente pede para falar com corretor
  - Negociação complexa
  - Reclamação/problema
- **Ação**: Notificar corretor via dashboard
- **Status Conversa**: 'aguardando_corretor'
- **Próximo**: Corretor assume e define próximo stage

---

## 🎯 Regras de Transição Inteligentes

### Progressão Automática
```
boas_vindas → coleta_dados → matching → apresentacao → interesse → agendamento → visita_agendada → pos_visita → negociacao → fechamento
```

### Loops de Ajuste
```
apresentacao → refinamento → matching → apresentacao (nova tentativa)
aguardando_info ↔ coleta_dados (até ter dados suficientes)
```

### Saídas do Funil
```
→ perdido (desistência)
→ inativo (sem resposta)
→ fechamento (sucesso!)
```

### Reengajamento
```
Qualquer stage → follow_up (se 3 dias sem resposta)
follow_up → (stage anterior) OU inativo
```

---

## 💡 Gatilhos de Mudança de Stage

### Automáticos (IA decide)
- Dados completos coletados → matching
- Imóveis encontrados → apresentacao
- Nenhum imóvel → sem_match
- Cliente pergunta sobre imóvel → interesse
- Solicita visita → agendamento

### Tempo-baseados
- 3 dias sem resposta → follow_up
- 7 dias sem resposta → inativo
- Após data de visita → pos_visita

### Manuais (Corretor)
- Corretor marca como 'proposta'
- Corretor marca como 'fechado'
- Corretor marca como 'perdido'

---

## 📈 KPIs por Stage

| Stage | Conversão Esperada | Tempo Médio |
|-------|-------------------|-------------|
| boas_vindas → coleta_dados | 85% | 2 min |
| coleta_dados → matching | 70% | 10 min |
| matching → apresentacao | 80% | Imediato |
| apresentacao → interesse | 40% | 1 hora |
| interesse → agendamento | 60% | 1 dia |
| agendamento → visita | 85% | 3 dias |
| visita → negociacao | 35% | 1 dia |
| negociacao → fechamento | 25% | 7 dias |

**Taxa de Conversão Total**: ~3-5% (leads → fechamento)

---

## 🚀 Próximas Evoluções

1. **Machine Learning**: Score preditivo de conversão por lead
2. **A/B Testing**: Testar diferentes mensagens por stage
3. **Remarketing**: Automação de follow-up inteligente
4. **Integração**: Calendário Google para agendamentos
5. **WhatsApp Business**: Catálogo de imóveis nativo
