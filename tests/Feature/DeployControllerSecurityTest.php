<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployControllerSecurityTest extends TestCase
{
    private ?string $originalDeploySecret = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDeploySecret = getenv('DEPLOY_SECRET') !== false ? getenv('DEPLOY_SECRET') : null;
        putenv('DEPLOY_SECRET=');
        $_ENV['DEPLOY_SECRET'] = '';
        $_SERVER['DEPLOY_SECRET'] = '';
    }

    protected function tearDown(): void
    {
        if ($this->originalDeploySecret === null) {
            putenv('DEPLOY_SECRET');
            unset($_ENV['DEPLOY_SECRET'], $_SERVER['DEPLOY_SECRET']);
        } else {
            putenv('DEPLOY_SECRET=' . $this->originalDeploySecret);
            $_ENV['DEPLOY_SECRET'] = $this->originalDeploySecret;
            $_SERVER['DEPLOY_SECRET'] = $this->originalDeploySecret;
        }

        parent::tearDown();
    }

    public function test_deploy_endpoint_is_disabled_without_a_configured_secret(): void
    {
        $response = $this->postJson('/api/deploy', [
            'project' => 'exclusiva',
        ]);

        $response
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_get_deploy_endpoint_is_not_exposed(): void
    {
        $this->getJson('/api/deploy')->assertStatus(405);
    }
}