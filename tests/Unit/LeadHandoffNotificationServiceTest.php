<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Services\ConversationAssignmentNotificationService;
use App\Services\LeadHandoffNotificationService;
use App\Services\LeadService;
use Mockery;
use Tests\TestCase;

class LeadHandoffNotificationServiceTest extends TestCase
{
    private function makeService($assignment): LeadHandoffNotificationService
    {
        $leadService = Mockery::mock(LeadService::class);
        $leadService->shouldReceive('normalizePhone')->andReturn(null);

        return new LeadHandoffNotificationService($assignment, $leadService);
    }

    private function makeLead(?string $notifiedFor = null): Lead
    {
        // Sem telefone: o texto livre não gera link curto (que gravaria no banco).
        return new Lead([
            'tenant_id' => 1,
            'nome' => 'Cliente Teste',
            'observacoes' => "💬 Mensagem: tenho interesse\n🔗 Origem: Chaves na Mão (ID: 1)",
            'atendimento_notificado_para' => $notifiedFor,
        ]);
    }

    public function test_identifica_leads_de_handoff(): void
    {
        $service = $this->makeService(Mockery::mock(ConversationAssignmentNotificationService::class));

        $this->assertTrue($service->isHandoffLead(new Lead(['observacoes' => 'Origem: Chaves na Mão'])));
        $this->assertTrue($service->isHandoffLead(new Lead(['observacoes' => '[Chat Portal] via mascote do portal'])));
        $this->assertFalse($service->isHandoffLead(new Lead(['observacoes' => 'Lead de indicação'])));
        $this->assertFalse($service->isHandoffLead(new Lead(['observacoes' => null])));
    }

    public function test_notify_nao_reenvia_para_quem_ja_foi_notificado(): void
    {
        $assignment = Mockery::mock(ConversationAssignmentNotificationService::class);
        // Roberto já recebeu: só a Alexsandra (template aprovado) deve ser acionada.
        $assignment->shouldReceive('sendWhatsAppTemplate')->once()->andReturn(['success' => true]);
        $assignment->shouldNotReceive('sendWhatsAppMessage');

        $notified = $this->makeService($assignment)->notify($this->makeLead('Roberto'));

        $this->assertEqualsCanonicalizing(['Roberto', 'Alexsandra'], $notified);
    }

    public function test_sucesso_do_roberto_nao_conta_como_alexsandra_notificada(): void
    {
        $assignment = Mockery::mock(ConversationAssignmentNotificationService::class);
        // Alexsandra: template e texto livre falham. Roberto: texto livre funciona.
        $assignment->shouldReceive('sendWhatsAppTemplate')->andReturn(['success' => false, 'error' => 'janela fechada']);
        $assignment->shouldReceive('sendWhatsAppMessage')
            ->twice()
            ->andReturnUsing(fn (int $tenantId, string $phone) => $phone === '5531997803274'
                ? ['success' => true]
                : ['success' => false, 'error' => 'falha']);

        $notified = $this->makeService($assignment)->notify($this->makeLead());

        $this->assertSame(['Roberto'], $notified);
        $this->assertNotContains('Alexsandra', $notified);
    }
}
