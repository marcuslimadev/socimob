<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Services\ConversationAssignmentNotificationService;
use App\Services\LeadDistributorAlertService;
use App\Services\LeadHandoffNotificationService;
use Illuminate\Support\Carbon;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class LeadDistributorAlertEligibilityTest extends TestCase
{
    private function isEligible(Lead $lead, bool $ignoreLimits): bool
    {
        $service = new LeadDistributorAlertService(
            Mockery::mock(ConversationAssignmentNotificationService::class),
            Mockery::mock(LeadHandoffNotificationService::class)
        );

        $method = new ReflectionMethod($service, 'isEligible');
        $method->setAccessible(true);

        return $method->invoke($service, $lead, $ignoreLimits);
    }

    private function lead(int $attempts, int $hoursOld): Lead
    {
        $lead = new Lead(['distribuidor_notificacao_tentativas' => $attempts]);
        $lead->created_at = Carbon::now()->subHours($hoursOld);

        return $lead;
    }

    public function test_lead_recente_com_poucas_tentativas_e_elegivel(): void
    {
        $this->assertTrue($this->isEligible($this->lead(2, 1), false));
    }

    public function test_reenvio_automatico_respeita_limite_de_tentativas(): void
    {
        $this->assertFalse($this->isEligible($this->lead(LeadDistributorAlertService::MAX_ATTEMPTS, 1), false));
    }

    public function test_reenvio_automatico_respeita_idade_maxima(): void
    {
        $this->assertFalse($this->isEligible($this->lead(0, LeadDistributorAlertService::MAX_AGE_HOURS + 1), false));
    }

    public function test_login_da_distribuidora_ignora_tentativas_e_idade(): void
    {
        $this->assertTrue($this->isEligible($this->lead(LeadDistributorAlertService::MAX_ATTEMPTS, 72), true));
    }
}
