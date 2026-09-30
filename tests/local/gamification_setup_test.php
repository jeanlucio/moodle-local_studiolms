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
}
