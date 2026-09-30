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
 * Unit tests for the course page builder's closing mind map.
 *
 * The page plan comes from the resolver's test seam; the mind map goes through the real
 * tiny_studiolms generator, whose only AI source here is a stubbed local_aihub client, so no
 * test reaches a real provider.
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
     * A blocks page ends with the editor's mind map, generated through the hub.
     *
     * Regression test: the editor's generator is called across plugins and inside a catch-all, so a
     * mismatched call (e.g. a missing context argument) would silently drop the mind map from every page.
     */
    public function test_blocks_page_ends_with_mind_map(): void {
        if (!class_exists(\local_aihub\ai::class)) {
            $this->markTestSkipped('local_aihub is not installed.');
        }
        set_config('gemini_key', 'stub-key', 'local_aihub');
        $mindmap = '{"topic":"Photosynthesis","branches":[{"label":"Light reactions","children":["Chlorophyll"]}]}';
        $client = new class ($mindmap) extends \local_aihub\local\client {
            /** @var int Number of generation requests received. */
            public int $calls = 0;

            /**
             * Constructor.
             *
             * @param string $data Text returned by every call.
             */
            public function __construct(
                /** @var string Text returned by every call. */
                private string $data
            ) {
            }

            #[\Override]
            public function generate_text(string $system, string $user, bool $jsonmode = false, ?int $userid = null): array {
                $this->calls++;
                $attempt = ['success' => true, 'provider' => 'Gemini', 'model' => 'stub', 'keysource' => 'site', 'message' => ''];
                return $attempt + ['data' => $this->data, 'attempts' => [$attempt]];
            }
        };
        \local_aihub\ai::set_client_for_testing($client);

        $html = $this->render_page();

        $this->assertSame(1, $client->calls);
        $this->assertStringContainsString('Photosynthesis basics', $html);
        $this->assertStringContainsString('data-slms-block-type="mindmap"', $html);
        $this->assertStringContainsString('Light reactions', $html);
    }

    /**
     * Without any AI source for the editor, the page keeps its blocks and just has no mind map.
     */
    public function test_page_without_editor_ai_degrades_to_blocks_only(): void {
        $html = $this->render_page();

        $this->assertStringContainsString('Photosynthesis basics', $html);
        $this->assertStringNotContainsString('data-slms-block-type="mindmap"', $html);
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
     * @param array $presets Preset definitions.
     * @return void
     */
    private function use_presets(array $presets): void {
        $base = make_request_directory();
        mkdir($base . '/en');
        foreach ($presets as $i => $preset) {
            file_put_contents($base . '/en/preset' . $i . '.json', json_encode($preset));
        }
        preset_loader::set_directory_for_testing($base);
    }

    /**
     * A fixture "Plano de Disciplina" preset with one heading and one callout holding placeholders.
     *
     * @return array The preset definition.
     */
    private function plan_preset(): array {
        return [
            'name' => 'Plano de Disciplina',
            'blocks' => [
                ['type' => 'stylizedHeading', 'config' => ['text' => 'Plan — [Nome da Disciplina]', 'level' => 'h3']],
                ['type' => 'callout', 'config' => [
                    'icon' => '📋',
                    'contentHtml' => '<strong>[Tópico 1]</strong> and <strong>fixed</strong>',
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
     * End-to-end reproduction of the reported PoC: a course intro built from the "Plano de Disciplina"
     * preset, with an AI fill response carrying an XSS payload, renders with the payload neutralized.
     *
     * Runs against a fixture catalog, so it does not depend on the editor's presets or on any language pack.
     */
    public function test_course_intro_neutralizes_malicious_ai_fill_end_to_end(): void {
        $this->use_presets([$this->plan_preset()]);
        $payload = json_encode([
            '[Tópico 1]' => '<img src=x onerror=fetch(1)>PWNED',
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
     * Without the "Plano de Disciplina" preset, the course intro falls back to AI-generated blocks.
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
        $this->use_presets([$this->plan_preset()]);
        ai_resolver::set_provider_for_testing(function (string $system, string $user): string {
            throw new \moodle_exception('invalidairesponse', 'local_studiolms');
        });
        $course = $this->getDataGenerator()->create_course();
        $degraded = false;

        $html = page_builder::render_course_intro(\context_course::instance($course->id), 'Biology', 'Course X', [], $degraded);

        $this->assertDebuggingCalled();
        $this->assertFalse($degraded);
        $this->assertStringContainsString('Plan — Course X', $html);
        $this->assertStringContainsString('[Tópico 1]', $html);
    }

    /**
     * A preset the AI plans is filled with sanitized values and reported as the chosen one.
     */
    public function test_ai_planned_preset_is_filled_and_sanitized(): void {
        $this->use_presets([$this->plan_preset()]);
        $plan = json_encode([
            'strategy' => 'preset',
            'preset_name' => 'plano de disciplina',
            'fill' => ['[Tópico 1]' => 'Cells <script>alert(1)</script> & tissues', '[Nome da Disciplina]' => 'Botany'],
        ]);
        ai_resolver::set_provider_for_testing(fn(string $system, string $user): string => $plan);
        $degraded = false;
        $chosen = '';

        $html = $this->render_with([], $degraded, $chosen);

        $this->assertFalse($degraded);
        $this->assertSame('plano de disciplina', $chosen);
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
     * render_preset() sanitizes whatever the preset renders, independently of fill_preset().
     *
     * The heading block trusts its text and passes it through, so this final pass is the only thing that
     * stops markup that reached a preset by another route.
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
}
