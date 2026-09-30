<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for the AI provider resolver.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the resolution order: test seam, AI Hub, then core_ai.
 *
 * The core_ai branch is not simulated: its manager API is static on Moodle 4.5 and an instance on 5.x, so a
 * mock that works on both does not exist. What is covered is the seam, the hub with a stubbed client, and the
 * "nothing configured" outcome that both fall back to.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ai_resolver::class)]
final class ai_resolver_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        ai_resolver::set_provider_for_testing(null);
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        if (class_exists(\local_aihub\ai::class)) {
            \local_aihub\ai::set_client_for_testing(null);
        }
        parent::tearDown();
    }

    /**
     * Installs a hub key and a stub hub client.
     *
     * @param bool $success Whether the stub reports a successful generation.
     * @return object The client, exposing the prompts it received in $prompts.
     */
    private function stub_hub(bool $success): object {
        if (!class_exists(\local_aihub\ai::class)) {
            $this->markTestSkipped('local_aihub is not installed.');
        }
        set_config('gemini_key', 'stub-key', 'local_aihub');
        $client = new class ($success) extends \local_aihub\local\client {
            /** @var array Prompts received, as [system, user] pairs. */
            public array $prompts = [];

            /**
             * Constructor.
             *
             * @param bool $success Whether calls succeed.
             */
            public function __construct(
                /** @var bool Whether calls succeed. */
                private bool $success
            ) {
            }

            #[\Override]
            public function generate_text(string $system, string $user, bool $jsonmode = false, ?int $userid = null): array {
                $this->prompts[] = [$system, $user];
                $attempt = [
                    'success' => $this->success, 'provider' => 'Gemini', 'model' => 'stub', 'keysource' => 'site',
                    'message' => $this->success ? '' : 'quota exceeded',
                ];
                return $attempt + ['data' => $this->success ? 'hub text' : '', 'attempts' => [$attempt]];
            }
        };
        \local_aihub\ai::set_client_for_testing($client);
        return $client;
    }

    /**
     * The injected provider wins, receives both prompts and its output is returned as a string.
     */
    public function test_test_provider_takes_precedence(): void {
        $seen = [];
        ai_resolver::set_provider_for_testing(static function (string $system, string $user) use (&$seen) {
            $seen = [$system, $user];
            return 42;
        });

        $this->assertTrue(ai_resolver::is_available());
        $this->assertSame('42', ai_resolver::generate_text('the system', 'the user'));
        $this->assertSame(['the system', 'the user'], $seen);
    }

    /**
     * Clearing the seam restores the real chain; with nothing configured nothing is available and asking fails.
     */
    public function test_nothing_configured_is_unavailable_and_refuses(): void {
        $this->assertFalse(ai_resolver::is_available());

        try {
            ai_resolver::generate_text('system', 'user');
            $this->fail('Generating without any AI source must fail.');
        } catch (\moodle_exception $e) {
            $this->assertSame('noaiprovider', $e->errorcode);
        }
    }

    /**
     * A hub with a usable key makes AI available, and its text is returned with the prompts passed through.
     */
    public function test_hub_is_used_when_it_has_a_key(): void {
        $client = $this->stub_hub(true);

        $this->assertTrue(ai_resolver::is_available());
        $this->assertSame('hub text', ai_resolver::generate_text('the system', 'the user'));
        $this->assertSame([['the system', 'the user']], $client->prompts);
    }

    /**
     * When the hub fails and nothing else is configured, the caller gets the "no provider" guidance.
     */
    public function test_hub_failure_without_core_ai_refuses(): void {
        $client = $this->stub_hub(false);

        try {
            ai_resolver::generate_text('system', 'user');
            $this->fail('A failed hub with no fallback must fail.');
        } catch (\moodle_exception $e) {
            $this->assertSame('noaiprovider', $e->errorcode);
            $this->assertCount(1, $client->prompts);
        }
    }
}
