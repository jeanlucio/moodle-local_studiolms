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
 * Tests for the course page builder.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studiolms\local;

/**
 * Unit tests for the course page builder.
 *
 * The page plan comes from the resolver's test seam, so no test reaches a real provider.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(page_builder::class)]
final class page_builder_test extends \advanced_testcase {
    /** @var string A blocks-strategy plan with one callout. */
    private const PLAN = '{"strategy":"blocks","blocks":[{"type":"callout","html":"<p>Photosynthesis basics</p>"}]}';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        if (\core_component::get_component_directory('tiny_studiolms') === null) {
            $this->markTestSkipped('tiny_studiolms editor not installed in this environment.');
        }
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => self::PLAN);
    }

    #[\Override]
    protected function tearDown(): void {
        ai_resolver::set_provider_for_testing(null);
        preset_loader::set_directory_for_testing(null);
        if (class_exists(\local_aihub\ai::class)) {
            \local_aihub\ai::set_client_for_testing(null);
        }
        parent::tearDown();
    }

    /**
     * Renders a content page for a fresh course.
     *
     * @return string Page HTML.
     */
    private function render_page(): string {
        $course = $this->getDataGenerator()->create_course();
        return page_builder::render(
            \context_course::instance($course->id),
            'Biology',
            'Plants',
            'Photosynthesis',
            []
        );
    }

    /**
     * Without any AI source for the editor, the page keeps its blocks.
     */
    public function test_page_without_editor_ai_degrades_to_blocks_only(): void {
        $html = $this->render_page();

        $this->assertStringContainsString('Photosynthesis basics', $html);
    }

    /**
     * fill_preset() only honours bracket-shaped placeholder keys that occur in the preset,
     * and reduces every replacement value to sanitized plain text.
     *
     * Regression test for a stored XSS: the AI-supplied fill map used to be substituted into
     * the preset's HTML with a raw str_replace(), and fields like contentHtml are rendered
     * through triple-mustache into a mod_page saved with format_text's noclean flag. A crafted
     * fill value (or an arbitrary, non-placeholder key) could inject markup that runs in every
     * viewer's browser.
     */
    public function test_fill_preset_sanitizes_ai_supplied_values(): void {
        $preset = [
            'blocks' => [
                [
                    'type' => 'callout',
                    'config' => [
                        'contentHtml' => '<strong>[Tópico 1]</strong> and <strong>fixed</strong>',
                    ],
                ],
            ],
        ];
        $fill = [
            '[Tópico 1]'      => '<img src=x onerror=alert(1)>PWNED',
            '<strong>'        => 'PWNED2',
            '[not in preset]' => 'ignored',
        ];

        $method = new \ReflectionMethod(page_builder::class, 'fill_preset');
        $method->setAccessible(true);
        $filled = $method->invoke(null, $preset, $fill);

        $html = $filled['blocks'][0]['config']['contentHtml'];
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('PWNED', $html);
        $this->assertStringContainsString('<strong>fixed</strong>', $html);
        $this->assertStringNotContainsString('PWNED2', $html);
    }

    /**
     * Points the preset loader at a fixture catalog holding the given presets, all under en/.
     *
     * @param array $presets Preset definitions; a string key is the file name (without .json).
     * @return void
     */
    private function use_presets(array $presets): void {
        $base = make_request_directory();
        mkdir($base . '/en');
        foreach ($presets as $i => $preset) {
            $file = is_string($i) ? $i : 'preset' . $i;
            file_put_contents($base . '/en/' . $file . '.json', json_encode($preset));
        }
        preset_loader::set_directory_for_testing($base);
    }

    /**
     * A fixture course plan preset (the English "Course Syllabus") with one heading and one callout holding placeholders.
     *
     * @return array The preset definition.
     */
    private function plan_preset(): array {
        return [
            'name' => 'Course Syllabus',
            'blocks' => [
                ['type' => 'stylizedHeading', 'config' => ['text' => 'Plan — [Course Name]', 'level' => 'h3']],
                ['type' => 'callout', 'config' => [
                    'icon' => '📋',
                    'contentHtml' => '<strong>[Topic 1]</strong> and <strong>fixed</strong>',
                ]],
            ],
        ];
    }

    /**
     * Renders a page for a fresh course with the given options.
     *
     * @param array $terms Glossary terms.
     * @param bool $degraded Set to true when the body could not be generated.
     * @param string $chosen Set to the chosen preset or 'blocks'.
     * @return string Page HTML.
     */
    private function render_with(array $terms, bool &$degraded, string &$chosen): string {
        $course = $this->getDataGenerator()->create_course();
        return page_builder::render(
            \context_course::instance($course->id),
            'Biology',
            'Plants',
            'Photosynthesis',
            $terms,
            '',
            'general',
            [],
            $degraded,
            $chosen
        );
    }









    /**
     * Markup fields of a preset are still sanitized, even though the rendered HTML no longer is.
     */
    public function test_preset_markup_fields_are_sanitized_before_rendering(): void {
        $preset = $this->plan_preset();
        $preset['blocks'][1]['config']['contentHtml'] = '<p>Safe</p><script>alert(1)</script><img src=x onerror=alert(2)>';

        $method = new \ReflectionMethod(page_builder::class, 'render_preset');
        $method->setAccessible(true);
        $html = $method->invoke(null, $preset);

        $this->assertStringContainsString('Safe', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('data-slms-block-type="callout"', $html);
    }

    /**
     * A preset the AI plans is filled with sanitized values and reported as the chosen one.
     */
    public function test_ai_planned_preset_is_filled_and_sanitized(): void {
        $this->use_presets(['course_syllabus' => $this->plan_preset()]);
        $plan = json_encode([
            'strategy' => 'preset',
            'preset_name' => 'course syllabus',
            'fill' => ['[Topic 1]' => 'Cells <script>alert(1)</script> & tissues', '[Course Name]' => 'Botany'],
        ]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $plan);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertFalse($degraded);
        $this->assertSame('course syllabus', $chosen);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('Plan — Botany', $html);
        $this->assertStringContainsString('&amp; tissues', $html);
    }

    /**
     * A preset the builder cannot draw completely is not applied, so the page never loses a block in silence.
     */
    public function test_a_preset_with_a_block_the_builder_cannot_draw_is_not_applied(): void {
        $this->use_presets(['lesson_tabs' => [
            'name' => 'Lesson in tabs',
            'description' => 'A lesson',
            'blocks' => [
                ['type' => 'stylizedHeading', 'config' => ['text' => 'Lesson [N]', 'level' => 'h2']],
                ['type' => 'tabs', 'config' => ['tabs' => [['title' => 'Read', 'content' => '<p>Body</p>']]]],
            ],
        ]]);
        $plan = json_encode([
            'strategy' => 'preset',
            'preset_name' => 'Lesson in tabs',
            'blocks' => [['type' => 'callout', 'html' => '<p>Planned block</p>']],
        ]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $plan);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertSame('blocks', $chosen);
        $this->assertStringContainsString('Planned block', $html);
        $this->assertStringNotContainsString('Lesson [N]', $html);
    }

    /**
     * The AI can answer a stretch of plain prose as a free text block, which is cleaned like any other markup.
     */
    public function test_a_free_text_block_is_drawn_and_cleaned(): void {
        $this->use_presets([]);
        $plan = json_encode([
            'strategy' => 'blocks',
            'blocks' => [
                ['type' => 'text', 'html' => '<p>Plain prose.</p><script>alert(1)</script>'],
                ['type' => 'text', 'html' => ''],
            ],
        ]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $plan);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertSame('blocks', $chosen);
        $this->assertSame(1, substr_count($html, 'data-slms-block-type="textBox"'));
        $this->assertStringContainsString('Plain prose.', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    /**
     * The planner is told it may write free text, and that the heading level and the prose are separate things.
     */
    public function test_the_plan_prompt_offers_free_text(): void {
        $this->use_presets([]);
        $seen = '';
        ai_resolver::set_provider_for_testing(function (string $system, string $user) use (&$seen): string {
            $seen = $system;
            return '{}';
        });
        $degraded = false;
        $chosen = '';

        $this->render_with([], $degraded, $chosen);

        $this->assertStringContainsString('"type":"text"', $seen);
    }

    /**
     * A preset the AI leaves with a placeholder unfilled is not published with the brackets in it: the page is asked
     * for again as free blocks.
     */
    public function test_a_preset_with_an_unfilled_placeholder_is_replaced_by_blocks(): void {
        $this->use_presets(['lesson' => [
            'name' => 'Lesson',
            'description' => 'A lesson',
            'blocks' => [['type' => 'stylizedHeading', 'config' => ['text' => 'Lesson [N] — [Title]', 'level' => 'h2']]],
        ]]);
        $systems = [];
        ai_resolver::set_provider_for_testing(function (string $system, string $user) use (&$systems): string {
            $systems[] = $system;
            if (count($systems) === 1) {
                return json_encode(['strategy' => 'preset', 'preset_name' => 'Lesson', 'fill' => ['[Title]' => 'Cells']]);
            }
            return json_encode(['strategy' => 'blocks', 'blocks' => [['type' => 'text', 'html' => '<p>Own words</p>']]]);
        });
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertCount(2, $systems);
        $this->assertStringNotContainsString('[PRESET]', $systems[1]);
        $this->assertStringNotContainsString('"strategy":"preset"', $systems[1]);
        $this->assertSame('blocks', $chosen);
        $this->assertStringContainsString('Own words', $html);
        $this->assertStringNotContainsString('[N]', $html);
    }

    /**
     * A preset the AI fills completely is used as it is, with a single call.
     */
    public function test_a_preset_filled_completely_costs_one_call(): void {
        $this->use_presets(['lesson' => [
            'name' => 'Lesson',
            'blocks' => [['type' => 'stylizedHeading', 'config' => ['text' => 'Lesson [N]', 'level' => 'h2']]],
        ]]);
        $calls = 0;
        ai_resolver::set_provider_for_testing(function () use (&$calls): string {
            $calls++;
            return json_encode(['strategy' => 'preset', 'preset_name' => 'Lesson', 'fill' => ['[N]' => '3']]);
        });
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertSame(1, $calls);
        $this->assertSame('Lesson', $chosen);
        $this->assertStringContainsString('Lesson 3', $html);
    }

    /**
     * The title of a heading the AI plans is text, which the block prints as it gets it, so it is escaped here once:
     * like the headings of the other builders, an ampersand or a bracket is not left raw in the page.
     */
    public function test_the_title_of_a_planned_heading_is_escaped_once(): void {
        $this->use_presets([]);
        $plan = json_encode(['strategy' => 'blocks', 'blocks' => [
            ['type' => 'heading', 'text' => 'Q & A: x < 10 "quoted"'],
        ]]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $plan);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertStringContainsString('Q &amp; A: x &lt; 10 &quot;quoted&quot;', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * A plan naming a preset that does not exist falls back to the blocks it carries.
     */
    public function test_unknown_preset_falls_back_to_the_plan_blocks(): void {
        $this->use_presets([]);
        $plan = json_encode([
            'strategy' => 'preset',
            'preset_name' => 'Missing',
            'blocks' => [['type' => 'callout', 'html' => '<p>Fallback block</p>']],
        ]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $plan);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertFalse($degraded);
        $this->assertSame('blocks', $chosen);
        $this->assertStringContainsString('Fallback block', $html);
    }

    /**
     * Without a usable plan the body is empty: the page is flagged degraded and shows just its title.
     */
    public function test_unusable_plan_degrades_to_the_title(): void {
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => 'not json');
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertTrue($degraded);
        $this->assertSame('', $chosen);
        $this->assertStringContainsString('Photosynthesis', $html);
    }

    /**
     * Plans a page whose text mentions the given words.
     *
     * @param string[] $words What the page says.
     * @return void
     */
    private function plan_page_saying(array $words): void {
        $plan = json_encode(['strategy' => 'blocks', 'blocks' => [
            ['type' => 'text', 'html' => '<p>' . implode(' and ', $words) . '</p>'],
        ]]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $plan);
    }

    /**
     * The pre-training block lists at most six of the glossary terms the page uses and escapes them.
     */
    public function test_pretraining_block_escapes_and_limits_terms(): void {
        $terms = [['term' => 'R&D', 'definition' => '<i>def</i>']];
        for ($i = 2; $i <= 8; $i++) {
            $terms[] = ['term' => 'Term' . $i, 'definition' => 'Definition' . $i];
        }
        $this->use_presets([]);
        $this->plan_page_saying(array_merge(['R&D'], array_column(array_slice($terms, 1), 'term')));
        $degraded = false;
        $chosen = '';

        $html = $this->render_with($terms, $degraded, $chosen);

        $this->assertStringNotContainsString('<i>def', $html);
        $this->assertStringContainsString('&lt;i&gt;def', $html);
        $this->assertStringContainsString('Definition6', $html);
        $this->assertStringNotContainsString('Definition7', $html);
    }

    /**
     * The key terms of a page are the ones it uses: the same list on every page would say nothing about any of them.
     */
    public function test_pretraining_block_holds_only_the_terms_the_page_uses(): void {
        $terms = [
            ['term' => 'Photosynthesis', 'definition' => 'Light into sugar'],
            ['term' => 'Two-factor authentication (2FA)', 'definition' => 'A second check'],
            ['term' => 'Mitosis', 'definition' => 'Cell division'],
        ];
        $this->use_presets([]);
        $this->plan_page_saying(['photosynthesis', 'the 2FA of the account']);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with($terms, $degraded, $chosen);

        $this->assertStringContainsString('Light into sugar', $html);
        $this->assertStringContainsString('A second check', $html);
        $this->assertStringNotContainsString('Cell division', $html);
    }

    /**
     * A page that uses no term of the glossary has no key terms block.
     */
    public function test_no_pretraining_block_when_the_page_uses_no_term(): void {
        $this->use_presets([]);
        $this->plan_page_saying(['something else']);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([['term' => 'Mitosis', 'definition' => 'Cell division']], $degraded, $chosen);

        $this->assertStringNotContainsString('Cell division', $html);
    }

    /**
     * Every custom block type is sanitized, incomplete blocks are skipped and unknown ones keep only clean HTML.
     */
    public function test_custom_blocks_are_sanitized_per_type(): void {
        $evil = '<script>alert(1)</script><img src="x" onerror="alert(2)">';
        $plan = json_encode(['strategy' => 'blocks', 'blocks' => [
            ['type' => 'heading', 'text' => 'Head ' . $evil],
            ['type' => 'heading'],
            ['type' => 'callout', 'html' => '<p>Callout text</p>' . $evil],
            ['type' => 'callout'],
            ['type' => 'card', 'content' => '<p>Card text</p>' . $evil],
            ['type' => 'card'],
            ['type' => 'accordion', 'title' => 'Title <b>t</b>', 'content' => '<p>Accordion text</p>' . $evil],
            ['type' => 'accordion'],
            ['type' => 'mystery', 'html' => '<p>Mystery text</p>' . $evil],
            ['type' => 'mystery'],
        ]]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $plan);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertFalse($degraded);
        $this->assertSame('blocks', $chosen);
        foreach (['Head', 'Callout text', 'Card text', 'Accordion text', 'Mystery text'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    /**
     * render_preset() sanitizes the fields the blocks print as markup, independently of fill_preset().
     *
     * The heading block trusts its text and passes it through, so this pass is the only thing that stops markup
     * that reached a preset by another route. It runs on the config, not on the rendered HTML, so the
     * data-slms-* contract of the blocks survives.
     */
    public function test_render_preset_sanitizes_the_final_html(): void {
        $preset = ['blocks' => [
            ['type' => 'stylizedHeading', 'config' => ['text' => 'Head <script>alert(1)</script>', 'level' => 'h3']],
            ['type' => 'callout', 'config' => ['icon' => '', 'contentHtml' => '<p>Ok</p><img src="x" onerror="alert(2)">']],
        ]];

        $method = new \ReflectionMethod(page_builder::class, 'render_preset');
        $method->setAccessible(true);
        $html = $method->invoke(null, $preset);

        $this->assertStringContainsString('Head', $html);
        $this->assertStringContainsString('Ok', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    /**
     * fill_preset() drops non-scalar values, keys that are not short bracketed placeholders, and escapes values.
     */
    public function test_fill_preset_ignores_malformed_entries_and_escapes_values(): void {
        $long = '[' . str_repeat('a', 81) . ']';
        $preset = ['blocks' => [['type' => 'callout', 'config' => [
            'contentHtml' => '[A] [B] [C] ' . $long . ' [D]',
        ]]]];
        $fill = ['[A]' => ['nested'], '[B]' => 'x & y', '[C]' => 3, $long => 'LONG', 0 => 'INT', 'D' => 'NOBRACKET'];

        $method = new \ReflectionMethod(page_builder::class, 'fill_preset');
        $method->setAccessible(true);
        $filled = $method->invoke(null, $preset, $fill);

        $this->assertSame('[A] x &amp; y 3 ' . $long . ' [D]', $filled['blocks'][0]['config']['contentHtml']);
    }

    /**
     * Blocks and block fields of the wrong type are skipped instead of throwing or printing "Array".
     *
     * Regression test: an entry that is not an object failed render_block()'s array signature, and an
     * array in a text field failed clean_param() or clean_text().
     */
    public function test_custom_blocks_survive_fields_of_the_wrong_type(): void {
        $plan = json_encode(['strategy' => 'blocks', 'blocks' => [
            'a string instead of a block',
            ['type' => 'heading', 'text' => ['x']],
            ['type' => 'callout', 'html' => ['<p>x</p>']],
            ['type' => 'card', 'content' => ['x']],
            ['type' => 'accordion', 'title' => ['x'], 'content' => 'Lost with its title'],
            ['type' => 'accordion', 'title' => 'Kept title', 'content' => ['x']],
            ['type' => 'mystery', 'html' => ['x']],
            ['type' => 'heading', 'text' => 'Survivor'],
        ]]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $plan);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertSame('blocks', $chosen);
        $this->assertStringContainsString('Survivor', $html);
        $this->assertStringContainsString('Kept title', $html);
        $this->assertStringNotContainsString('Lost with its title', $html);
        $this->assertStringNotContainsString('Array', $html);
    }

    /**
     * The level, the objectives and the reference material of the page reach the plan prompt.
     */
    public function test_the_plan_prompt_carries_the_level_the_objectives_and_the_reference(): void {
        $seen = [];
        ai_resolver::set_provider_for_testing(function (string $system, string $user) use (&$seen): string {
            $seen[] = $system . "\n" . $user;
            return self::PLAN;
        });
        $course = $this->getDataGenerator()->create_course();
        $degraded = false;

        page_builder::render(
            \context_course::instance($course->id),
            'Biology',
            'Plants',
            'Photosynthesis',
            [],
            'Reference text here',
            'apply',
            ['Understand light', 'Draw a diagram'],
            $degraded
        );

        $this->assertFalse($degraded);
        $prompt = implode("\n", $seen);
        $this->assertStringContainsString('apply', $prompt);
        $this->assertStringContainsString('Understand light; Draw a diagram', $prompt);
        $this->assertStringContainsString('Reference text here', $prompt);
    }

    /**
     * When the AI fails, or names a preset that does not exist and gives no blocks, the page is only its title and
     * is reported as degraded.
     */
    public function test_a_page_without_a_usable_plan_is_its_title_and_degraded(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $answers = [
            'throws' => static function (): string {
                throw new \RuntimeException('No provider.');
            },
            'missing preset' => static fn(): string => '{"strategy":"preset","preset_name":"nope"}',
        ];

        foreach ($answers as $case => $provider) {
            ai_resolver::set_provider_for_testing($provider);
            $degraded = false;

            $html = page_builder::render($context, 'Biology', 'Plants', 'Photosynthesis', [], '', 'general', [], $degraded);

            $this->assertTrue($degraded, $case);
            $this->assertSame('<p>Photosynthesis</p>', $html, $case);
        }
    }
}
