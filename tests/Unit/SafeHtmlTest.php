<?php

namespace Tests\Unit;

use App\Support\Html\SafeHtml;
use PHPUnit\Framework\TestCase;

class SafeHtmlTest extends TestCase
{
    public function test_it_keeps_allowed_markup_and_strips_scripts(): void
    {
        $html = SafeHtml::render('<p>Hola <strong>mundo</strong></p><script>alert(1)</script>');

        $this->assertStringContainsString('<p>Hola <strong>mundo</strong></p>', $html);
        $this->assertStringNotContainsString('script', strtolower($html));
    }

    public function test_it_removes_event_handlers_and_javascript_urls(): void
    {
        $html = SafeHtml::render('<p onclick="alert(1)">x</p><a href="javascript:alert(1)">y</a>');

        $this->assertStringNotContainsString('onclick', strtolower($html));
        $this->assertStringNotContainsString('javascript:', strtolower($html));
    }
}
