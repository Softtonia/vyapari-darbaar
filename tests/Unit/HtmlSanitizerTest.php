<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizerService;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    protected HtmlSanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizerService();
    }

    public function test_strips_script_tags(): void
    {
        $input = '<p>Hello <script>alert("XSS")</script>world</p>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script', $cleaned);
        $this->assertStringNotContainsString('alert', $cleaned);
        $this->assertStringContainsString('<p>Hello world</p>', $cleaned);
    }

    public function test_strips_inline_event_handlers(): void
    {
        $input = '<p onclick="steal()">Click me</p><a href="https://example.com" onmouseover="hack()">Link</a>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('onclick', $cleaned);
        $this->assertStringNotContainsString('onmouseover', $cleaned);
        $this->assertStringContainsString('<p>Click me</p>', $cleaned);
        $this->assertStringContainsString('href="https://example.com"', $cleaned);
    }

    public function test_blocks_javascript_urls(): void
    {
        $input = '<a href="javascript:alert(1)">Click</a><a href="https://safe.com">Safe</a>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:alert(1)', $cleaned);
        $this->assertStringContainsString('href="https://safe.com"', $cleaned);
    }

    public function test_enforces_noopener_on_blank_target(): void
    {
        $input = '<a href="https://example.com" target="_blank">External</a>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('rel="noopener noreferrer"', $cleaned);
    }

    public function test_allows_safe_editorial_markup(): void
    {
        $input = '<h1>Title</h1><p>Paragraph with <b>bold</b> and <i>italic</i> and <a href="https://example.com">link</a></p>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('<h1>Title</h1>', $cleaned);
        $this->assertStringContainsString('<b>bold</b>', $cleaned);
        $this->assertStringContainsString('<i>italic</i>', $cleaned);
    }
}
