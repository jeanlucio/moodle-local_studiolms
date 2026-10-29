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
 * Tests for the gamification setup helper.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the gamification setup helper.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(gamification_setup::class)]
final class gamification_setup_test extends \advanced_testcase {
    /**
     * The social profile reports one extra step (the hourly forum collectible).
     *
     * @return void
     */
    public function test_step_count_per_profile(): void {
        $this->assertSame(8, gamification_setup::step_count(gamification_setup::PROFILE_SOCIAL));
        $this->assertSame(7, gamification_setup::step_count(gamification_setup::PROFILE_CONQUEST));
        $this->assertSame(7, gamification_setup::step_count(gamification_setup::PROFILE_NARRATIVE));
    }

    /**
     * Availability reflects whether the PlayerHUD block plugin is installed.
     *
     * @return void
     */
    public function test_is_available_matches_plugin_list(): void {
        $expected = array_key_exists('playerhud', \core_component::get_plugin_list('block'));
        $this->assertSame($expected, gamification_setup::is_available());
    }

    /**
     * The block is for whoever may add it to the course: the right to add that block and to manage blocks.
     *
     * @return void
     */
    public function test_can_use_needs_the_rights_to_add_the_block(): void {
        global $DB;
        $this->resetAfterTest();
        if (!gamification_setup::is_available()) {
            $this->markTestSkipped('block_playerhud is not installed in this environment.');
        }
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->assertTrue(gamification_setup::can_use($context, (int) $teacher->id));
        $this->assertFalse(gamification_setup::can_use($context, (int) $student->id));

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('block/playerhud:addinstance', CAP_PREVENT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(gamification_setup::can_use($context, (int) $teacher->id));
    }

    /**
     * Builds a setup object for a profile without running it, so its private helpers can be called.
     *
     * @param string $profile The profile.
     * @param array $properties Extra private properties to set.
     * @return gamification_setup
     */
    private function setup_for(string $profile, array $properties = []): gamification_setup {
        $setup = (new \ReflectionClass(gamification_setup::class))->newInstanceWithoutConstructor();
        foreach (['profile' => $profile] + $properties as $name => $value) {
            $property = new \ReflectionProperty(gamification_setup::class, $name);
            $property->setAccessible(true);
            $property->setValue($setup, $value);
        }
        return $setup;
    }

    /**
     * Calls a private instance method.
     *
     * @param gamification_setup $setup The object.
     * @param string $method The method name.
     * @param mixed ...$args Its arguments.
     * @return mixed The result.
     */
    private function call(gamification_setup $setup, string $method, mixed ...$args): mixed {
        $reflection = new \ReflectionMethod(gamification_setup::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($setup, ...$args);
    }

    /**
     * Each profile scales rewards and plans drops differently.
     *
     * @return void
     */
    public function test_profiles_scale_rewards_and_drops(): void {
        $expected = [
            gamification_setup::PROFILE_CONQUEST => [1.5, [3, 3]],
            gamification_setup::PROFILE_NARRATIVE => [0.7, [1, 1]],
            gamification_setup::PROFILE_SOCIAL => [1.0, [2, 3]],
        ];
        foreach ($expected as $profile => [$multiplier, $plan]) {
            $setup = $this->setup_for($profile);
            $this->assertSame($multiplier, $this->call($setup, 'xp_multiplier'), $profile);
            $this->assertSame($plan, $this->call($setup, 'drop_plan'), $profile);
        }
    }

    /**
     * The block config keeps the story tab available and hides the ranking only for the narrative profile.
     *
     * @return void
     */
    public function test_block_config_follows_the_profile(): void {
        $narrative = $this->call($this->setup_for(gamification_setup::PROFILE_NARRATIVE), 'profile_config');
        $conquest = $this->call($this->setup_for(gamification_setup::PROFILE_CONQUEST), 'profile_config');

        $this->assertSame(0, $narrative->enable_ranking);
        $this->assertSame(1, $conquest->enable_ranking);
        foreach ([$narrative, $conquest] as $config) {
            $this->assertSame(1, $config->enable_rpg);
            $this->assertSame(1, $config->enable_items);
            $this->assertSame(1, $config->enable_quests);
            $this->assertSame(0, $config->enable_group_ranking);
        }
    }

    /**
     * The drop shortcode is put in front of the existing introduction, and a missing module is reported.
     *
     * @return void
     */
    public function test_drop_shortcode_is_prepended_to_the_intro(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'intro' => '<p>Intro</p>']);
        $bare = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $DB->set_field('quiz', 'intro', '', ['id' => $bare->id]);
        $method = new \ReflectionMethod(gamification_setup::class, 'inject_drop_shortcode');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, (int) $quiz->cmid, 'abc123'));
        $this->assertTrue($method->invoke(null, (int) $bare->cmid, 'def456'));
        $this->assertFalse($method->invoke(null, 999999, 'nope'));

        $this->assertSame(
            '[PLAYERHUD_DROP code=abc123]<br><p>Intro</p>',
            $DB->get_field('quiz', 'intro', ['id' => $quiz->id])
        );
        $this->assertSame('[PLAYERHUD_DROP code=def456]', $DB->get_field('quiz', 'intro', ['id' => $bare->id]));
    }

    /**
     * A failing write rolls the transaction back and closes it before the exception reaches the caller.
     *
     * Regression test: a transaction left open by a swallowed failure makes the cron fail the task and run it again.
     *
     * @return void
     */
    public function test_in_transaction_rolls_back_and_closes_on_failure(): void {
        global $DB;
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $setup = $this->setup_for(gamification_setup::PROFILE_CONQUEST);
        $record = (object) ['plugin' => 'local_studiolms', 'name' => 'txprobe', 'value' => '1'];

        try {
            $this->call($setup, 'in_transaction', static function () use ($DB, $record): void {
                $DB->insert_record('config_plugins', $record);
                throw new \RuntimeException('write failed');
            });
            $this->fail('The failure must reach the caller.');
        } catch (\RuntimeException $e) {
            $this->assertSame('write failed', $e->getMessage());
        }

        $this->assertFalse($DB->is_transaction_started());
        $this->assertFalse($DB->record_exists('config_plugins', ['plugin' => 'local_studiolms', 'name' => 'txprobe']));
    }

    /**
     * The writes of a successful transaction are kept and the transaction is closed.
     *
     * @return void
     */
    public function test_in_transaction_commits_on_success(): void {
        global $DB;
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $setup = $this->setup_for(gamification_setup::PROFILE_CONQUEST);

        $this->call($setup, 'in_transaction', static function () use ($DB): void {
            $DB->insert_record('config_plugins', (object) ['plugin' => 'local_studiolms', 'name' => 'txprobe', 'value' => '1']);
        });

        $this->assertFalse($DB->is_transaction_started());
        $this->assertTrue($DB->record_exists('config_plugins', ['plugin' => 'local_studiolms', 'name' => 'txprobe']));
    }

    /**
     * The block instance is inserted in the course's side region, after the blocks already there, with the
     * profile config, and the step is reported.
     *
     * @return void
     */
    public function test_block_is_inserted_at_the_end_of_the_side_region(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $DB->insert_record('block_instances', (object) [
            'blockname' => 'html', 'parentcontextid' => $context->id, 'showinsubcontexts' => 0,
            'requiredbytheme' => 0, 'pagetypepattern' => 'course-view-*', 'defaultregion' => 'side-pre',
            'defaultweight' => 5, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $messages = [];
        $setup = $this->setup_for(gamification_setup::PROFILE_NARRATIVE, [
            'course' => $course,
            'advance' => \Closure::fromCallable(static function (string $message) use (&$messages): void {
                $messages[] = $message;
            }),
        ]);

        $this->call($setup, 'setup_block');

        $block = $DB->get_record(
            'block_instances',
            ['blockname' => 'playerhud', 'parentcontextid' => $context->id],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(6, $block->defaultweight);
        $this->assertSame('side-pre', $block->defaultregion);
        $config = unserialize_object(base64_decode($block->configdata));
        $this->assertSame(0, $config->enable_ranking);
        $this->assertSame([get_string('progress_playerhud', 'local_studiolms')], $messages);
    }

    /**
     * The block is a real instance for the core: its own context exists and the block loads with the profile config.
     *
     * @return void
     */
    public function test_block_loads_through_the_core_with_its_config_and_context(): void {
        global $DB;
        $this->resetAfterTest();
        if (!gamification_setup::is_available()) {
            $this->markTestSkipped('block_playerhud is not installed in this environment.');
        }
        $course = $this->getDataGenerator()->create_course();
        $setup = $this->setup_for(gamification_setup::PROFILE_SOCIAL, [
            'course' => $course,
            'advance' => \Closure::fromCallable(static function (string $message): void {
            }),
        ]);

        $this->call($setup, 'setup_block');

        $record = $DB->get_record('block_instances', [
            'blockname' => 'playerhud',
            'parentcontextid' => \context_course::instance($course->id)->id,
        ], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('context', ['contextlevel' => CONTEXT_BLOCK, 'instanceid' => $record->id]));
        $block = block_instance('playerhud', $record);
        $this->assertSame(1, $block->config->enable_ranking);
        $this->assertSame('course-view-*', $record->pagetypepattern);
    }

    /**
     * The full setup reports one step per phase, adds the block, and an unknown profile behaves as conquest.
     *
     * Needs the PlayerHUD block, whose generators the setup drives, so it only runs where that is installed.
     *
     * @return void
     */
    public function test_run_reports_every_step_and_falls_back_to_conquest(): void {
        global $DB;
        if (!gamification_setup::is_available()) {
            $this->markTestSkipped('block_playerhud is not installed in this environment.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $steps = 0;

        $warnings = gamification_setup::run(
            $course,
            'bogus',
            'Botany',
            [],
            [],
            static function (string $message) use (&$steps): void {
                $steps++;
            }
        );

        $this->assertIsArray($warnings);
        $this->assertSame(gamification_setup::step_count(gamification_setup::PROFILE_CONQUEST), $steps);
        $block = $DB->get_record('block_instances', [
            'blockname' => 'playerhud',
            'parentcontextid' => \context_course::instance($course->id)->id,
        ], '*', MUST_EXIST);
        $this->assertSame(1, unserialize_object(base64_decode($block->configdata))->enable_ranking);
    }

    /**
     * Makes a course with a quiz, an assignment and a forum, and runs the setup on them.
     *
     * @param string $profile The gamification profile.
     * @param array $sectiontitles The section titles.
     * @param int $steps Receives how many steps were reported.
     * @return string[] The warnings of the run.
     */
    private function run_on_activities(string $profile, array $sectiontitles, int &$steps): array {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator();
        $cmids = [
            'quiz' => [(int) $generator->create_module('quiz', ['course' => $course->id])->cmid],
            'assign' => [(int) $generator->create_module('assign', ['course' => $course->id])->cmid],
            'forum' => [(int) $generator->create_module('forum', ['course' => $course->id])->cmid],
        ];
        $steps = 0;

        return gamification_setup::run(
            $course,
            $profile,
            'Botany',
            $cmids,
            $sectiontitles,
            static function (string $message) use (&$steps): void {
                $steps++;
            }
        );
    }

    /**
     * With activities of every kind, every profile reports every step, whatever the AI answers, and a failing AI
     * only adds warnings: the course is never left half done.
     *
     * @return void
     */
    public function test_run_reports_every_step_with_activities_and_a_failing_ai(): void {
        if (!gamification_setup::is_available()) {
            $this->markTestSkipped('block_playerhud is not installed in this environment.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();

        foreach ([gamification_setup::PROFILE_CONQUEST, 'narrative', 'social'] as $profile) {
            ai_resolver::set_provider_for_testing(static function (): string {
                throw new \RuntimeException('No provider.');
            });
            $steps = 0;
            $warnings = $this->run_on_activities($profile, ['Roots', 'Leaves'], $steps);
            $this->assertIsArray($warnings, $profile);
            $this->assertSame(gamification_setup::step_count($profile), $steps, $profile);

            ai_resolver::set_provider_for_testing(static fn(): string => '{"items": [], "content": "x"}');
            $steps = 0;
            $this->assertIsArray($this->run_on_activities($profile, [], $steps), $profile);
            $this->assertSame(gamification_setup::step_count($profile), $steps, $profile);
        }
        ai_resolver::set_provider_for_testing(null);
        $this->resetDebugging();
    }

    /**
     * Run by someone who may not set PlayerHUD up, the steps that need that permission fail one by one: each failure
     * is a warning and every step is still reported, so the course is never left half done.
     *
     * @return void
     */
    public function test_run_by_a_user_without_permission_warns_and_still_reports_every_step(): void {
        if (!gamification_setup::is_available()) {
            $this->markTestSkipped('block_playerhud is not installed in this environment.');
        }
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $cmids = [
            'quiz' => [(int) $generator->create_module('quiz', ['course' => $course->id])->cmid],
            'forum' => [(int) $generator->create_module('forum', ['course' => $course->id])->cmid],
        ];
        $this->setUser($student);
        $steps = 0;

        $warnings = gamification_setup::run(
            $course,
            'social',
            'Botany',
            $cmids,
            ['Roots'],
            static function (string $message) use (&$steps): void {
                $steps++;
            }
        );

        $this->assertSame(gamification_setup::step_count('social'), $steps);
        $this->assertNotEmpty($warnings);
        $this->resetDebugging();
    }
}
