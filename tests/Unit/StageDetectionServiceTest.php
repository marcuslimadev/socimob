<?php

namespace Tests\Unit;

use App\Services\StageDetectionService;
use Tests\TestCase;

class StageDetectionServiceTest extends TestCase
{
    public function test_detect_next_stage_reads_history_string_context(): void
    {
        $service = new StageDetectionService();

        $stage = $service->detectNextStage('coleta_dados', 'Pode seguir', [
            'history' => "Cliente: Meu nome é Ana Silva\nAtendente: Qual faixa de valor você busca?",
        ]);

        $this->assertSame('orcamento', $stage);
    }

    public function test_detect_next_stage_reads_message_arrays(): void
    {
        $service = new StageDetectionService();

        $stage = $service->detectNextStage('orcamento', 'Pode seguir', [
            ['content' => 'Procuro até 600 mil'],
        ]);

        $this->assertSame('localizacao', $stage);
    }
}
