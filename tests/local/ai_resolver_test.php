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
 * The core_ai branch is simulated with a fake manager put in the dependency container, which is how the resolver
 * gets the manager on every supported version. The rest is the seam, the hub with a stubbed client, and the
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
        \core\di::reset_container();
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

    /**
     * The canned answer that Behat scenarios store in a config is ignored outside Behat, so it cannot replace the AI
     * in production.
     */
    public function test_the_behat_canned_answer_is_inert_outside_behat(): void {
        $this->stub_hub(false);
        set_config('behatfakeai_response', '{"blocks":[]}', 'local_studiolms');

        try {
            ai_resolver::generate_text('system', 'user');
            $this->fail('The canned answer must not be used outside Behat.');
        } catch (\moodle_exception $e) {
            $this->assertSame('noaiprovider', $e->errorcode);
        }
    }

    /**
     * Puts a fake core_ai manager in the container.
     *
     * @param bool $enabled What the course switch for AI tools says.
     * @param string|null $content What the provider generates; null for a failure.
     * @param bool $hasswitch False for a manager that has no per-course switch, as before Moodle 5.0.
     * @return object The manager, exposing the actions it processed in $actions and the contexts it was asked about.
     */
    private function fake_core_ai(bool $enabled, ?string $content, bool $hasswitch = true): object {
        $response = new class ($content) {
            /**
             * Constructor.
             *
             * @param string|null $content What was generated.
             */
            public function __construct(
                /** @var string|null What was generated. */
                private ?string $content
            ) {
            }

            /**
             * Whether the generation worked.
             *
             * @return bool
             */
            public function get_success(): bool {
                return $this->content !== null;
            }

            /**
             * The data of the response.
             *
             * @return array
             */
            public function get_response_data(): array {
                return ['generatedcontent' => $this->content];
            }
        };
        $manager = new class ($response, $enabled) {
            /** @var array The actions processed. */
            public array $actions = [];

            /** @var array The contexts the switch was asked about. */
            public array $asked = [];

            /**
             * Constructor.
             *
             * @param object $response The response to every action.
             * @param bool $enabled What the switch says.
             */
            public function __construct(
                /** @var object The response to every action. */
                private object $response,
                /** @var bool What the switch says. */
                private bool $enabled
            ) {
            }

            /**
             * The providers of the actions: one for each.
             *
             * @param array $actions The action classes.
             * @param bool $enabledonly Whether only enabled providers count.
             * @return array
             */
            public function get_providers_for_actions(array $actions, bool $enabledonly): array {
                return [$actions[0] => ['provider']];
            }

            /**
             * Processes an action.
             *
             * @param object $action The action.
             * @return object
             */
            public function process_action(object $action): object {
                $this->actions[] = $action;
                return $this->response;
            }

            /**
             * What the per-course switch says.
             *
             * @param \context $context The context.
             * @param string $actionclass The action class.
             * @return bool
             */
            public function is_action_enabled_in_context(\context $context, string $actionclass): bool {
                $this->asked[] = $context->id;
                return $this->enabled;
            }
        };
        $exposed = $manager;
        if (!$hasswitch) {
            // A manager as it was before Moodle 5.0: the same, with no per-course switch to ask.
            $exposed = new class ($manager) {
                /**
                 * Constructor.
                 *
                 * @param object $inner The manager that does the work.
                 */
                public function __construct(
                    /** @var object The manager that does the work. */
                    private object $inner
                ) {
                }

                /**
                 * The providers of the actions.
                 *
                 * @param array $actions The action classes.
                 * @param bool $enabledonly Whether only enabled providers count.
                 * @return array
                 */
                public function get_providers_for_actions(array $actions, bool $enabledonly): array {
                    return $this->inner->get_providers_for_actions($actions, $enabledonly);
                }

                /**
                 * Processes an action.
                 *
                 * @param object $action The action.
                 * @return object
                 */
                public function process_action(object $action): object {
                    return $this->inner->process_action($action);
                }
            };
        }
        \core\di::set(\core_ai\manager::class, $exposed);
        return $manager;
    }

    /**
     * Without the hub, the text comes from core_ai, with the system prompt put before the user prompt, and the
     * action is for the context the text is for.
     */
    public function test_core_ai_generates_the_text_for_the_context_given(): void {
        if (!class_exists(\core_ai\aiactions\generate_text::class)) {
            $this->markTestSkipped('core_ai is not available.');
        }
        $manager = $this->fake_core_ai(true, 'core text');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->setAdminUser();

        $this->assertTrue(ai_resolver::is_available());
        $this->assertSame('core text', ai_resolver::generate_text('the system', 'the user', $context));

        $this->assertCount(1, $manager->actions);
        $this->assertSame($context->id, $manager->actions[0]->get_configuration('contextid'));
        $this->assertSame("the system\n\nthe user", $manager->actions[0]->get_configuration('prompttext'));
        $this->assertSame([$context->id], $manager->asked);

        ai_resolver::generate_text('', 'only the user');
        $this->assertSame(\context_system::instance()->id, $manager->actions[1]->get_configuration('contextid'));
        $this->assertSame('only the user', $manager->actions[1]->get_configuration('prompttext'));
    }

    /**
     * A course with the AI tools off gets no text, and the AI is not asked.
     */
    public function test_a_course_with_the_ai_tools_off_is_refused_by_core_ai(): void {
        if (!class_exists(\core_ai\aiactions\generate_text::class)) {
            $this->markTestSkipped('core_ai is not available.');
        }
        $manager = $this->fake_core_ai(false, 'core text');
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        try {
            ai_resolver::generate_text('system', 'user', \context_course::instance($course->id));
            $this->fail('A course with the AI tools off must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('aidisabledincourse', $e->errorcode);
        }
        $this->assertSame([], $manager->actions);
    }

    /**
     * Before Moodle 5.0 there is no switch per course, so there is nothing to respect and the text is generated.
     */
    public function test_a_manager_with_no_course_switch_generates_the_text(): void {
        if (!class_exists(\core_ai\aiactions\generate_text::class)) {
            $this->markTestSkipped('core_ai is not available.');
        }
        $this->fake_core_ai(false, 'core text', false);
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        $this->assertSame('core text', ai_resolver::generate_text('s', 'u', \context_course::instance($course->id)));
    }

    /**
     * A failed generation or an empty one is reported as an invalid answer.
     */
    public function test_a_failed_core_ai_generation_is_refused(): void {
        if (!class_exists(\core_ai\aiactions\generate_text::class)) {
            $this->markTestSkipped('core_ai is not available.');
        }
        $this->fake_core_ai(true, null);
        $this->setAdminUser();

        try {
            ai_resolver::generate_text('system', 'user');
            $this->fail('A failed generation must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidairesponse', $e->errorcode);
        }
    }
}
