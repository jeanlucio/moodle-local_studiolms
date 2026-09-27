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
}
