<?php

namespace Tests\Unit\Services;

use App\Services\EmailBodyRenderer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EmailBodyRendererTest extends CIUnitTestCase
{
    public function testIsHtmlBodyDetectsMarkup(): void
    {
        $this->assertTrue(EmailBodyRenderer::isHtmlBody('<p>Hello</p>'));
        $this->assertTrue(EmailBodyRenderer::isHtmlBody('Intro <a href="x">link</a>'));
        $this->assertFalse(EmailBodyRenderer::isHtmlBody("Hello there\nMaps: https://example.com"));
    }

    public function testPlainTextSafetyNetWrapsAndAutolinksWithFriendlyLabels(): void
    {
        $renderer = new EmailBodyRenderer();
        $body = "See you soon!\n"
            . "Maps: https://www.google.com/maps/search/?api=1&query=Sandton\n"
            . "Waze: https://waze.com/ul?q=Sandton&navigate=yes";

        $html = $renderer->render($body, 'Reminder');

        // Wrapped in the responsive shell.
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('email-container', $html);
        // Raw URLs converted to friendly clickable anchors.
        $this->assertStringContainsString('Open in Google Maps', $html);
        $this->assertStringContainsString('Open in Waze', $html);
        $this->assertStringContainsString('<a href="https://www.google.com/maps', $html);
        // The long raw URL is not shown as visible link text.
        $this->assertStringNotContainsString('>https://www.google.com/maps', $html);
    }

    public function testHtmlFragmentPassesThroughIntoShell(): void
    {
        $renderer = new EmailBodyRenderer();
        $fragment = '<p class="greeting">Hi Jane</p><a class="btn" href="https://example.com/manage">Manage</a>';

        $html = $renderer->render($fragment, 'Confirmed');

        $this->assertStringContainsString('<p class="greeting">Hi Jane</p>', $html);
        $this->assertStringContainsString('<title>Confirmed</title>', $html);
        $this->assertStringContainsString('email-container', $html);
    }

    public function testPlainTextEscapesAngleBracketsToPreventMarkupInjection(): void
    {
        $renderer = new EmailBodyRenderer();

        $html = $renderer->render('Hello <script>alert(1)</script> world');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testToPlainTextFlattensHtmlForMultipartAlt(): void
    {
        $renderer = new EmailBodyRenderer();

        $alt = $renderer->toPlainText('<p>Hello</p><p>World</p><a href="https://x">Go</a>');

        $this->assertStringContainsString('Hello', $alt);
        $this->assertStringContainsString('World', $alt);
        $this->assertStringNotContainsString('<p>', $alt);
    }

    public function testToPlainTextLeavesPlainTextUnchanged(): void
    {
        $renderer = new EmailBodyRenderer();
        $body = "Hello there\nMaps: https://example.com";

        $this->assertSame($body, $renderer->toPlainText($body));
    }
}
