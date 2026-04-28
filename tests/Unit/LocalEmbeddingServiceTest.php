<?php

namespace Tests\Unit;

use App\Services\LocalEmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalEmbeddingServiceTest extends TestCase
{
    public function test_embed_text_batch_sends_task_type_and_returns_metadata(): void
    {
        Http::fake([
            '127.0.0.1:8000/embed' => Http::response([
                'success' => true,
                'embeddings' => [[0.1, 0.2, 0.3]],
                'model' => 'nomic-ai/nomic-embed-text-v1.5',
                'dimensions' => 3,
            ]),
        ]);

        $service = new LocalEmbeddingService();

        $result = $service->embedTextBatch(['cobertura com vista'], 'search_query');

        $this->assertTrue($result['success']);
        $this->assertSame([[0.1, 0.2, 0.3]], $result['embeddings']);
        $this->assertSame(3, $result['dimensions']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8000/embed'
                && $request['texts'] === ['cobertura com vista']
                && $request['task_type'] === 'search_query';
        });
    }

    public function test_embed_text_exposes_first_embedding(): void
    {
        Http::fake([
            '127.0.0.1:8000/embed' => Http::response([
                'success' => true,
                'embeddings' => [[0.4, 0.5]],
                'model' => 'nomic-ai/nomic-embed-text-v1.5',
                'dimensions' => 2,
            ]),
        ]);

        $service = new LocalEmbeddingService();

        $result = $service->embedText('apartamento perto do centro');

        $this->assertTrue($result['success']);
        $this->assertSame([0.4, 0.5], $result['embedding']);
    }
}
