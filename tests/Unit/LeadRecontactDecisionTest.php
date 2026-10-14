<?php

namespace Tests\Unit;

use App\Services\LeadDistributorAlertService as Alert;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class LeadRecontactDecisionTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-10-14 12:00:00');
    }

    private function decide(string $mode, bool $hasBroker, ?int $lastContactDaysAgo, ?int $lastAlertMinutesAgo = null, int $days = 7): bool
    {
        return Alert::shouldAlertRecontact(
            $mode,
            $days,
            $hasBroker,
            $lastContactDaysAgo === null ? null : $this->now->copy()->subDays($lastContactDaysAgo),
            $lastAlertMinutesAgo === null ? null : $this->now->copy()->subMinutes($lastAlertMinutesAgo),
            $this->now
        );
    }

    public function test_desligado_nunca_avisa(): void
    {
        $this->assertFalse($this->decide(Alert::RECONTACT_MODE_OFF, false, 90));
    }

    public function test_modo_todos_avisa_mesmo_com_corretor_e_contato_recente(): void
    {
        $this->assertTrue($this->decide(Alert::RECONTACT_MODE_ALL, true, 1));
    }

    public function test_modo_filtrado_avisa_quando_nao_ha_corretor(): void
    {
        $this->assertTrue($this->decide(Alert::RECONTACT_MODE_FILTERED, false, 1));
    }

    public function test_modo_filtrado_nao_avisa_com_corretor_e_contato_recente(): void
    {
        $this->assertFalse($this->decide(Alert::RECONTACT_MODE_FILTERED, true, 3));
    }

    public function test_modo_filtrado_avisa_com_corretor_quando_parado_ha_x_dias_ou_mais(): void
    {
        $this->assertTrue($this->decide(Alert::RECONTACT_MODE_FILTERED, true, 7));
        $this->assertTrue($this->decide(Alert::RECONTACT_MODE_FILTERED, true, 30));
        $this->assertFalse($this->decide(Alert::RECONTACT_MODE_FILTERED, true, 6));
    }

    public function test_modo_filtrado_respeita_o_valor_de_dias_configurado(): void
    {
        $this->assertTrue($this->decide(Alert::RECONTACT_MODE_FILTERED, true, 3, null, 3));
        $this->assertFalse($this->decide(Alert::RECONTACT_MODE_FILTERED, true, 2, null, 3));
    }

    public function test_sem_contato_anterior_registrado_avisa_no_modo_filtrado(): void
    {
        $this->assertTrue($this->decide(Alert::RECONTACT_MODE_FILTERED, true, null));
    }

    public function test_intervalo_minimo_entre_avisos_vale_para_todos_os_modos(): void
    {
        $this->assertFalse($this->decide(Alert::RECONTACT_MODE_ALL, false, 90, 10));
        $this->assertFalse($this->decide(Alert::RECONTACT_MODE_FILTERED, false, 90, 10));
        $this->assertTrue($this->decide(Alert::RECONTACT_MODE_ALL, false, 90, Alert::RECONTACT_COOLDOWN_MINUTES + 1));
    }

    public function test_intervalo_minimo_do_aviso_ao_corretor(): void
    {
        $this->assertTrue(Alert::cooldownElapsed(null, $this->now));
        $this->assertFalse(Alert::cooldownElapsed($this->now->copy()->subMinutes(10), $this->now));
        $this->assertTrue(Alert::cooldownElapsed($this->now->copy()->subMinutes(Alert::RECONTACT_COOLDOWN_MINUTES + 1), $this->now));
    }

    public function test_modo_desconhecido_cai_no_comportamento_filtrado(): void
    {
        $this->assertFalse($this->decide('qualquer-coisa', true, 1));
        $this->assertTrue($this->decide('qualquer-coisa', false, 1));
    }
}
