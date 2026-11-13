<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentTemplateSecurityTest extends TestCase
{
    public function test_environment_template_does_not_ship_a_pre_generated_application_key(): void
    {
        $contents = file_get_contents(base_path('.env.example'));

        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression('/^APP_KEY=\s*$/m', $contents);
    }

    public function test_environment_template_uses_utc_as_its_default_timezone(): void
    {
        $contents = file_get_contents(base_path('.env.example'));

        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression('/^APP_TIMEZONE="UTC"$/m', $contents);
    }
}
