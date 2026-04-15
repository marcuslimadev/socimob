<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\WhatsApp\DispatchWhatsAppOutboundMessageJob;
use App\Models\WhatsApp\WhatsAppContact;
use App\Models\WhatsApp\WhatsAppConversation;
use App\Models\WhatsApp\WhatsAppMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class WhatsAppMessageApiTest extends WhatsAppFeatureTestCase
{
    public function test_it_queues_a_text_message_within_the_open_window(): void
    {
        Queue::fake();
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $connection = $this->createConnection($tenant);

        $contact = WhatsAppContact::query()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_phone_number_id' => $connection['phoneNumber']->id,
            'phone_e164' => '5511988887777',
            'contact_name' => 'Maria',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        WhatsAppConversation::query()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_account_id' => $connection['account']->id,
            'whatsapp_phone_number_id' => $connection['phoneNumber']->id,
            'whatsapp_contact_id' => $contact->id,
            'status' => 'open',
            'started_at' => now()->subHour(),
            'last_message_at' => now()->subMinutes(5),
            'last_inbound_at' => now()->subMinutes(5),
            'expires_at' => now()->addHours(23),
        ]);

        $response = $this->withHeaders($this->adminHeaders($user, $tenant))
            ->postJson('/api/whatsapp/messages/text', [
                'to' => '5511988887777',
                'body' => 'Mensagem livre',
                'idempotency_key' => 'message-text-1',
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message.status', 'queued');

        $this->assertDatabaseCount('whatsapp_messages', 1);
        Queue::assertPushed(DispatchWhatsAppOutboundMessageJob::class, 1);
    }

    public function test_it_rejects_free_form_message_outside_the_open_window(): void
    {
        Queue::fake();
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $connection = $this->createConnection($tenant);

        $contact = WhatsAppContact::query()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_phone_number_id' => $connection['phoneNumber']->id,
            'phone_e164' => '5511977776666',
            'contact_name' => 'João',
            'first_seen_at' => now()->subDays(2),
            'last_seen_at' => now()->subDays(2),
        ]);

        WhatsAppConversation::query()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_account_id' => $connection['account']->id,
            'whatsapp_phone_number_id' => $connection['phoneNumber']->id,
            'whatsapp_contact_id' => $contact->id,
            'status' => 'open',
            'started_at' => now()->subDays(2),
            'last_message_at' => now()->subDays(2),
            'last_inbound_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->withHeaders($this->adminHeaders($user, $tenant))
            ->postJson('/api/whatsapp/messages/text', [
                'to' => '5511977776666',
                'body' => 'Fora da janela',
            ]);

        $response->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_it_queues_a_media_message_with_file_upload(): void
    {
        Queue::fake();
        Storage::fake('local');
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant);
        $connection = $this->createConnection($tenant);

        $contact = WhatsAppContact::query()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_phone_number_id' => $connection['phoneNumber']->id,
            'phone_e164' => '5511988887777',
            'contact_name' => 'Carlos',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        WhatsAppConversation::query()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_account_id' => $connection['account']->id,
            'whatsapp_phone_number_id' => $connection['phoneNumber']->id,
            'whatsapp_contact_id' => $contact->id,
            'status' => 'open',
            'started_at' => now()->subHour(),
            'last_message_at' => now()->subMinutes(5),
            'last_inbound_at' => now()->subMinutes(5),
            'expires_at' => now()->addHours(23),
        ]);

        $response = $this->withHeaders($this->adminHeaders($user, $tenant))
            ->post('/api/whatsapp/messages/media', [
                'to' => '5511988887777',
                'media_type' => 'document',
                'caption' => 'Contrato',
                'filename' => 'contrato.pdf',
                'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('success', true);

        $message = WhatsAppMessage::query()->first();
        $this->assertNotNull($message);
        $this->assertSame('document', $message->message_type);
        $this->assertArrayHasKey('stored_file_path', $message->payload);
        Queue::assertPushed(DispatchWhatsAppOutboundMessageJob::class, 1);
    }
}
