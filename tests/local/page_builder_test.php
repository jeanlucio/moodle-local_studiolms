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
     * End-to-end reproduction of the reported PoC: a course intro built from the course plan
     * preset, with an AI fill response carrying an XSS payload, renders with the payload neutralized.
     *
     * Runs against a fixture catalog, so it does not depend on the editor's presets or on any language pack.
     */
    public function test_course_intro_neutralizes_malicious_ai_fill_end_to_end(): void {
        $this->use_presets(['course_syllabus' => $this->plan_preset()]);
        $payload = json_encode([
            '[Topic 1]' => '<img src=x onerror=fetch(1)>PWNED',
            '<strong>' => 'PWNED2',
        ]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $payload);
        $course = $this->getDataGenerator()->create_course();
        $degraded = false;

        $html = page_builder::render_course_intro(\context_course::instance($course->id), 'Biology', 'Course X', [], $degraded);

        $this->assertFalse($degraded);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('PWNED2', $html);
        $this->assertStringContainsString('PWNED', $html);
        $this->assertStringContainsString('Plan — Course X', $html);
        $this->assertStringContainsString('<strong>fixed</strong>', $html);
    }

    /**
     * Without a course plan preset, the course intro falls back to AI-generated blocks.
     *
     * Regression test: the fallback called generate_body() without its context argument, which shifted every
     * argument and raised a TypeError swallowed by the catch-all, so the intro page silently came out empty
     * (degraded) whenever the preset catalog lacked that preset.
     */
    public function test_course_intro_falls_back_to_blocks_when_preset_is_missing(): void {
        $this->use_presets([]);
        $course = $this->getDataGenerator()->create_course();
        $degraded = false;

        $html = page_builder::render_course_intro(
            \context_course::instance($course->id),
            'Biology',
            'Course X',
            [],
            $degraded
        );

        $this->assertFalse($degraded);
        $this->assertStringContainsString('Photosynthesis basics', $html);
    }

    /**
     * When the AI cannot fill the intro, the preset is still rendered with its base values.
     */
    public function test_course_intro_survives_a_failing_fill(): void {
        $this->use_presets(['course_syllabus' => $this->plan_preset()]);
        ai_resolver::set_provider_for_testing(function (string $system, string $user): string {
            throw new \moodle_exception('invalidairesponse', 'local_studiolms');
        });
        $course = $this->getDataGenerator()->create_course();
        $degraded = false;

        $html = page_builder::render_course_intro(\context_course::instance($course->id), 'Biology', 'Course X', [], $degraded);

        $this->assertDebuggingCalled();
        $this->assertFalse($degraded);
        $this->assertStringContainsString('Plan — Course X', $html);
        $this->assertStringContainsString('[Topic 1]', $html);
    }

    /**
     * The course plan keeps the contract the editor needs to reopen its blocks (data-slms-* attributes).
     *
     * Regression test: a clean_text() over the rendered HTML removed the attributes, so the blocks of every
     * preset page opened as locked HTML.
     */
    public function test_course_intro_keeps_the_editor_contract(): void {
        $this->use_presets(['course_syllabus' => $this->plan_preset()]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => '{}');
        $course = $this->getDataGenerator()->create_course();
        $degraded = false;

        $html = page_builder::render_course_intro(\context_course::instance($course->id), 'Biology', 'Course X', [], $degraded);

        $this->assertStringContainsString('data-slms-block-type="callout"', $html);
        $this->assertStringContainsString('data-slms-state="', $html);
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
     * Reports whether the course plan preset was used or the blocks fallback took its place.
     */
    public function test_course_intro_reports_whether_the_plan_was_applied(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $degraded = false;

        $this->use_presets(['course_syllabus' => $this->plan_preset()]);
        $applied = false;
        page_builder::render_course_intro($context, 'Biology', 'Course X', [], $degraded, $applied);
        $this->assertTrue($applied);

        $this->use_presets([]);
        page_builder::render_course_intro($context, 'Biology', 'Course X', [], $degraded, $applied);
        $this->assertFalse($applied);
    }

    /**
     * Every placeholder the course plan expects exists in the real catalog files, so a renamed placeholder in
     * tiny_studiolms is caught here instead of silently leaving the plan unfilled.
     */
    public function test_course_plan_placeholders_exist_in_the_real_catalog(): void {
        $constant = new \ReflectionClassConstant(page_builder::class, 'COURSE_PLAN_PRESETS');
        $lang = ['course_syllabus' => 'en', 'plano_de_disciplina' => 'pt_br'];
        foreach ($constant->getValue() as $stem => $placeholders) {
            $preset = preset_loader::find_by_file($stem, $lang[$stem]);
            $this->assertNotNull($preset, $stem);
            $json = json_encode($preset, JSON_UNESCAPED_UNICODE);
            foreach (array_merge([$placeholders['name']], $placeholders['ai']) as $token) {
                $this->assertStringContainsString($token, $json, $stem . ' ' . $token);
            }
        }
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
     * The pre-training block lists at most six glossary terms and escapes them.
     */
    public function test_pretraining_block_escapes_and_limits_terms(): void {
        $terms = [['term' => '<b>Bad</b>', 'definition' => '<i>def</i>']];
        for ($i = 2; $i <= 8; $i++) {
            $terms[] = ['term' => 'Term' . $i, 'definition' => 'Definition' . $i];
        }
        $degraded = false;
        $chosen = '';

        $html = $this->render_with($terms, $degraded, $chosen);

        $this->assertStringNotContainsString('<b>Bad', $html);
        $this->assertStringContainsString('&lt;b&gt;Bad', $html);
        $this->assertStringContainsString('Term6', $html);
        $this->assertStringNotContainsString('Term7', $html);
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
}
