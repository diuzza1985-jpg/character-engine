<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Fix 4 (3 settembre 2026): un post fallito (Post#50) restava invisibile finché nessuno
 * controllava a mano laravel.log o il pannello admin — nessuna notifica, nessun modo rapido di
 * accorgersene. Gli ERROR (Log::error, come già fa PublishInstagramPostJob::failed()) finiscono
 * ora anche in un file dedicato (storage/logs/alerts.log), sgombro dal resto del traffico
 * INFO/WARNING — non un test sul contenuto scritto su disco, solo che il canale sia cablato bene.
 */
class AlertsLogChannelTest extends TestCase
{
    public function test_the_default_stack_includes_a_dedicated_alerts_channel(): void
    {
        $this->assertContains('alerts', config('logging.channels.stack.channels'));
    }

    public function test_the_alerts_channel_only_captures_error_level_and_above(): void
    {
        $this->assertSame('error', config('logging.channels.alerts.level'));
        $this->assertStringEndsWith('alerts.log', config('logging.channels.alerts.path'));
    }
}
