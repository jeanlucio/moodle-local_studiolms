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
 * Configures PlayerHUD gamification for a generated course.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

use stdClass;

/**
 * Adds and configures a PlayerHUD block on a course, reusing the block's own
 * deterministic generators (PlayerCoin, avatars, trade and quest suggestions)
 * and its AI generators (story chapter, themed item drops).
 *
 * All work is additive and degrades gracefully: a failure in any single phase
 * is recorded as a warning instead of aborting the course generation.
 */
class gamification_setup {
    /** @var string Gamification profile favouring accomplishment and XP. */
    public const PROFILE_CONQUEST = 'conquest';

    /** @var string Gamification profile favouring narrative and discovery. */
    public const PROFILE_NARRATIVE = 'narrative';

    /** @var string Gamification profile favouring social interaction. */
    public const PROFILE_SOCIAL = 'social';

    /** @var int The PlayerHUD block instance ID being configured. */
    private int $instanceid = 0;

    /** @var stdClass The target course. */
    private stdClass $course;

    /** @var string The gamification profile. */
    private string $profile;

    /** @var string The course theme. */
    private string $theme;

    /** @var array Course module ids grouped by module name (quiz, assign, forum). */
    private array $cmidsbytype;

    /** @var string[] Section titles, used by the narrative profile. */
    private array $sectiontitles;

    /** @var \Closure Progress callback receiving a message string. */
    private $advance;

    /** @var string[] Warnings collected for phases that degraded. */
    private array $warnings = [];

    /**
     * Whether the PlayerHUD block plugin is installed.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return array_key_exists('playerhud', \core_component::get_plugin_list('block'));
    }

    /**
     * Lists the core capabilities needed to add the block to a course, as a teacher does on the course page.
     *
     * @return string[] Capability names.
     */
    public static function required_capabilities(): array {
        return ['moodle/site:manageblocks', 'block/playerhud:addinstance'];
    }

    /**
     * Tells whether a user may have the gamified mode: the block is installed and the user may add it to the course.
     *
     * The setup writes the block straight into the course, so the rights a teacher needs to add it from the course
     * page are checked here instead of by the page.
     *
     * @param \context_course $context Course context.
     * @param int|null $userid The user, or null for the current one.
     * @return bool
     */
    public static function can_use(\context_course $context, ?int $userid = null): bool {
        if (!self::is_available()) {
            return false;
        }
        foreach (self::required_capabilities() as $capability) {
            if (!has_capability($capability, $context, $userid)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Number of progress steps run() will report for a given profile.
     *
     * @param string $profile The gamification profile.
     * @return int
     */
    public static function step_count(string $profile): int {
        return $profile === self::PROFILE_SOCIAL ? 8 : 7;
    }

    /**
     * Runs the full gamification setup.
     *
     * @param stdClass $course The target course.
     * @param string $profile The gamification profile (conquest, narrative, social).
     * @param string $theme The course theme.
     * @param array $cmidsbytype Course module ids grouped by module name.
     * @param array $sectiontitles Section titles for narrative chapters.
     * @param callable $advance Progress callback receiving a message string.
     * @return string[] Warnings for phases that degraded.
     */
    public static function run(
        stdClass $course,
        string $profile,
        string $theme,
        array $cmidsbytype,
        array $sectiontitles,
        callable $advance
    ): array {
        $setup = new self();
        $setup->course = $course;
        $setup->profile = in_array($profile, [
            self::PROFILE_CONQUEST,
            self::PROFILE_NARRATIVE,
            self::PROFILE_SOCIAL,
        ], true) ? $profile : self::PROFILE_CONQUEST;
        $setup->theme = $theme;
        $setup->cmidsbytype = $cmidsbytype;
        $setup->sectiontitles = $sectiontitles;
        $setup->advance = \Closure::fromCallable($advance);

        $setup->setup_block();
        $setup->setup_playercoin();
        $setup->setup_avatars();
        $setup->setup_trades();
        $setup->setup_quests();
        $setup->setup_story();
        $setup->setup_drops();
        if ($setup->profile === self::PROFILE_SOCIAL) {
            $setup->setup_social_forum_collectible();
        }

        // The drop shortcodes were written into the activities' intro fields directly; without this the cached
        // course structure keeps serving the intro as it was.
        rebuild_course_cache($course->id, true);

        return $setup->warnings;
    }

    /**
     * Adds the PlayerHUD block to the course and applies the profile config.
     *
     * The block is added through the block manager, as the core does for the default blocks of a new course, so its
     * context and its own set-up hook are made by the core. The manager is given the course and the region itself:
     * the generation runs in a background task with no real page or theme, where the manager cannot choose a
     * default region.
     *
     * @return void
     */
    private function setup_block(): void {
        global $DB;

        $coursecontext = \context_course::instance($this->course->id);

        $weight = (int) $DB->get_field_sql(
            "SELECT MAX(defaultweight)
               FROM {block_instances}
              WHERE parentcontextid = :ctx AND defaultregion = :region",
            ['ctx' => $coursecontext->id, 'region' => 'side-pre']
        );

        $page = new \moodle_page();
        $page->set_course($this->course);
        $page->blocks->add_regions(['side-pre'], false);
        $block = $page->blocks->add_block('playerhud', 'side-pre', $weight + 1, false, 'course-view-*');
        $this->instanceid = (int) $block->instance->id;
        $block->instance_config_save($this->profile_config());

        $coursecontext->mark_dirty();

        ($this->advance)(get_string('progress_playerhud', 'local_studiolms'));
    }

    /**
     * Builds the block instance config object for the active profile.
     *
     * @return stdClass
     */
    private function profile_config(): stdClass {
        $config = new stdClass();
        $config->xp_per_level = 100;
        $config->max_levels = 20;
        $config->enable_items = 1;
        $config->enable_quests = 1;
        // RPG mode stays on: the narrative chapters tab depends on this flag.
        $config->enable_rpg = 1;
        // Group ranking needs course groups, which this plugin does not set up.
        $config->enable_group_ranking = 0;
        // The narrative profile keeps the focus on the story, hiding the ranking.
        $config->enable_ranking = $this->profile === self::PROFILE_NARRATIVE ? 0 : 1;
        $config->use_default_help = 1;
        return $config;
    }

    /**
     * Creates the PlayerCoin item and its infinite drop in the news forum.
     *
     * @return void
     */
    private function setup_playercoin(): void {
        try {
            $result = \block_playerhud\external\create_playercoin::execute($this->instanceid, $this->course->id);
            $itemid = (int) ($result['itemid'] ?? 0);
            if ($itemid > 0) {
                \block_playerhud\external\setup_playercoin_drop::execute($this->instanceid, $this->course->id, $itemid);
            }
        } catch (\Throwable $e) {
            $this->warnings[] = get_string('progress_playercoin', 'local_studiolms');
        }
        ($this->advance)(get_string('progress_playercoin', 'local_studiolms'));
    }

    /**
     * Creates the predefined avatar item pack.
     *
     * @return void
     */
    private function setup_avatars(): void {
        try {
            \block_playerhud\external\create_avatar_pack::execute($this->instanceid, $this->course->id);
        } catch (\Throwable $e) {
            $this->warnings[] = get_string('progress_avatars', 'local_studiolms');
        }
        ($this->advance)(get_string('progress_avatars', 'local_studiolms'));
    }

    /**
     * Creates all heuristic trade suggestions (PlayerCoin to avatars).
     *
     * @return void
     */
    private function setup_trades(): void {
        $count = 0;
        try {
            $suggestions = \block_playerhud\game::build_trade_suggestions($this->instanceid);
            $this->in_transaction(function () use ($suggestions, &$count): void {
                foreach ($suggestions as $sug) {
                    \block_playerhud\game::create_trade_from_suggestion($this->instanceid, $sug);
                    $count++;
                }
            });
        } catch (\Throwable $e) {
            $count = 0;
            $this->warnings[] = get_string('progress_trades', 'local_studiolms', 0);
        }
        ($this->advance)(get_string('progress_trades', 'local_studiolms', $count));
    }

    /**
     * Runs the work in one transaction, rolled back and rethrown when it fails.
     *
     * Callers that turn a failure into a warning must go through here: a delegated transaction left open by a
     * swallowed exception would absorb everything the task does afterwards, and the cron would then fail the task
     * ("Task left transaction open") and run the whole course generation again.
     *
     * @param callable $work The writes to run atomically.
     * @return void
     */
    private function in_transaction(callable $work): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $work();
        } catch (\Throwable $e) {
            // Rolls back and rethrows the exception it is given.
            $transaction->rollback($e);
        }
        $transaction->allow_commit();
    }

    /**
     * Creates heuristic quests filtered and scaled by the active profile.
     *
     * @return void
     */
    private function setup_quests(): void {
        global $DB;

        $count = 0;
        try {
            $bi = $DB->get_record('block_instances', ['id' => $this->instanceid], '*', MUST_EXIST);
            $config = unserialize_object(base64_decode($bi->configdata));
            if (!is_object($config)) {
                $config = new stdClass();
            }

            $suggestions = \block_playerhud\quest::get_heuristic_suggestions(
                $this->instanceid,
                $this->course->id,
                $config
            );

            $allowedtypes = $this->quest_types_for_profile();
            $multiplier = $this->xp_multiplier();

            $records = [];
            foreach ($suggestions as $sug) {
                if (!in_array((int) $sug['type'], $allowedtypes, true)) {
                    continue;
                }
                $xp = (int) round(((int) $sug['reward_xp']) * $multiplier);
                $records[] = \block_playerhud\quest::build_record_from_suggestion($this->instanceid, $sug, $xp);
            }

            $count = count($records);
            if ($count > 0) {
                $DB->insert_records('block_playerhud_quests', $records);
            }
        } catch (\Throwable $e) {
            $this->warnings[] = get_string('progress_quests', 'local_studiolms', 0);
        }
        ($this->advance)(get_string('progress_quests', 'local_studiolms', $count));
    }

    /**
     * Generates the narrative story chapters for the active profile.
     *
     * @return void
     */
    private function setup_story(): void {
        $themes = [$this->theme];
        if ($this->profile === self::PROFILE_NARRATIVE && !empty($this->sectiontitles)) {
            $themes = array_map(fn($title) => $this->theme . ' — ' . $title, $this->sectiontitles);
        }

        $created = 0;
        foreach ($themes as $storytheme) {
            try {
                $result = \block_playerhud\external\generate_story::execute(
                    $this->instanceid,
                    $this->course->id,
                    \core_text::substr($storytheme, 0, 250)
                );
                if (!empty($result['success'])) {
                    $created++;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        if ($created === 0) {
            $this->warnings[] = get_string('progress_story', 'local_studiolms');
        }
        ($this->advance)(get_string('progress_story', 'local_studiolms'));
    }

    /**
     * Generates themed AI item drops and injects them into key activities.
     *
     * @return void
     */
    private function setup_drops(): void {
        $targets = array_merge(
            $this->cmidsbytype['quiz'] ?? [],
            $this->cmidsbytype['assign'] ?? []
        );

        if (empty($targets) || !$this->ai_items_enabled()) {
            ($this->advance)(get_string('progress_drops', 'local_studiolms', 0));
            return;
        }

        [$dropcount, $dropmax] = $this->drop_plan();
        $targets = array_slice($targets, 0, $dropcount);

        $created = 0;
        try {
            $generator = new \block_playerhud\ai\generator($this->instanceid);
            foreach ($targets as $cmid) {
                $result = $generator->generate('item', $this->theme, -1, true, [
                    'drop_max' => $dropmax,
                    'drop_time' => 0,
                ], 1);
                $code = $result['drop_code'] ?? '';
                if ($code !== '' && self::inject_drop_shortcode((int) $cmid, $code)) {
                    $created++;
                }
            }
        } catch (\Throwable $e) {
            if ($created === 0) {
                $this->warnings[] = get_string('progress_drops', 'local_studiolms', 0);
            }
        }
        ($this->advance)(get_string('progress_drops', 'local_studiolms', $created));
    }

    /**
     * Creates an hourly collectible item dropped in every generated forum.
     *
     * Compensates for the absence of group activities in the social profile:
     * forums are the course's interaction hubs, so a recurring collectible there
     * rewards ongoing participation.
     *
     * @return void
     */
    private function setup_social_forum_collectible(): void {
        $forums = $this->cmidsbytype['forum'] ?? [];

        $created = 0;
        if (!empty($forums) && $this->ai_items_enabled()) {
            try {
                $generator = new \block_playerhud\ai\generator($this->instanceid);
                $result = $generator->generate('item', $this->theme, 0, true, [
                    'drop_max' => 0,
                    'drop_time' => 60,
                ], 1);
                $code = $result['drop_code'] ?? '';
                if ($code !== '') {
                    foreach ($forums as $cmid) {
                        if (self::inject_drop_shortcode((int) $cmid, $code)) {
                            $created++;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->warnings[] = get_string('progress_social_forum', 'local_studiolms');
            }
        }
        ($this->advance)(get_string('progress_social_forum', 'local_studiolms'));
    }

    /**
     * Quest suggestion types kept for the active profile.
     *
     * @return int[]
     */
    private function quest_types_for_profile(): array {
        $activity = \block_playerhud\quest::TYPE_ACTIVITY;
        $level = \block_playerhud\quest::TYPE_LEVEL;
        $items = \block_playerhud\quest::TYPE_UNIQUE_ITEMS;
        $trades = \block_playerhud\quest::TYPE_TRADES;

        switch ($this->profile) {
            case self::PROFILE_NARRATIVE:
                return [$level, $items];
            case self::PROFILE_SOCIAL:
                return [$activity, $items, $trades];
            default:
                return [$activity, $level, $items, $trades];
        }
    }

    /**
     * XP reward multiplier for the active profile.
     *
     * @return float
     */
    private function xp_multiplier(): float {
        switch ($this->profile) {
            case self::PROFILE_NARRATIVE:
                return 0.7;
            case self::PROFILE_SOCIAL:
                return 1.0;
            default:
                return 1.5;
        }
    }

    /**
     * Number of activity drops and their max usage for the active profile.
     *
     * @return int[] A two-element list: the drop count and the per-drop max usage.
     */
    private function drop_plan(): array {
        switch ($this->profile) {
            case self::PROFILE_NARRATIVE:
                return [1, 1];
            case self::PROFILE_SOCIAL:
                return [2, 3];
            default:
                return [3, 3];
        }
    }

    /**
     * Whether AI item generation should be attempted.
     *
     * @return bool
     */
    private function ai_items_enabled(): bool {
        return class_exists('\block_playerhud\ai\generator');
    }

    /**
     * Appends a PlayerHUD drop shortcode to a course module intro field.
     *
     * @param int $cmid The course module ID.
     * @param string $code The drop code.
     * @return bool True when the intro was updated.
     */
    private static function inject_drop_shortcode(int $cmid, string $code): bool {
        global $DB;

        $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return false;
        }

        $shortcode = '[PLAYERHUD_DROP code=' . $code . ']';
        $intro = (string) $DB->get_field($cm->modname, 'intro', ['id' => $cm->instance]);
        $newintro = $shortcode . ($intro !== '' ? '<br>' . $intro : '');
        course_builder::write_module_text($cmid, $newintro);

        return true;
    }
}
