<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $payloadArray = json_decode($payload, true);

        $signature = $request->header('X-Hub-Signature-256') ?? $request->header('X-Hub-Signature');
        $secret = env('GITHUB_WEBHOOK_SECRET');

        if ($secret && !$this->isValidSignature($payload, $signature, $secret)) {
            Log::warning('GitHub webhook signature mismatch', [
                'event' => $request->header('X-GitHub-Event'),
                'delivery' => $request->header('X-GitHub-Delivery'),
                'signature' => $signature,
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        if (!$secret) {
            Log::warning('GitHub webhook secret is empty; skipping signature validation');
        }

        $event = $request->header('X-GitHub-Event', 'unknown');
        $delivery = $request->header('X-GitHub-Delivery');

        Log::info('GitHub webhook received', [
            'event' => $event,
            'delivery' => $delivery,
            'repository' => data_get($payloadArray, 'repository.full_name'),
            'ref' => data_get($payloadArray, 'ref'),
            'pusher' => data_get($payloadArray, 'pusher.name'),
        ]);

        if ($event === 'ping') {
            Log::info('GitHub webhook ping responded');
            return response()->json(['message' => 'pong']);
        }

        if ($event === 'push') {
            $ref = data_get($payloadArray, 'ref', 'unknown branch');
            $before = data_get($payloadArray, 'before');
            $after = data_get($payloadArray, 'after');
            $repository = data_get($payloadArray, 'repository.full_name');

            Log::info("GitHub push event received for {$ref}", [
                'repository' => $repository,
                'before' => $before,
                'after' => $after,
            ]);
        }

        return response()->json(['received' => true], 202);
    }

    private function isValidSignature(string $payload, ?string $signatureHeader, string $secret): bool
    {
        if (empty($signatureHeader) || strpos($signatureHeader, '=') === false) {
            return false;
        }

        [$algo, $hash] = explode('=', $signatureHeader, 2);
        $algo = strtolower($algo);

        if (!in_array($algo, hash_algos(), true)) {
            return false;
        }

        $computed = hash_hmac($algo, $payload, $secret);

        return hash_equals($computed, $hash);
    }
}
