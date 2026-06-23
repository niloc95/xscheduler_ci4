<?php

namespace App\Services;

/**
 * Wraps a rendered notification email body in the responsive HTML shell
 * (app/Views/emails/notification.php) and guarantees it is valid HTML.
 *
 * Two input shapes are supported:
 *
 *  1. HTML fragment — the redesigned customer email templates
 *     (NotificationTemplateService::DEFAULT_TEMPLATES + the V6 settings rows).
 *     Used as-is for the shell content area.
 *
 *  2. Plain text — internal (provider/staff) templates and any admin-customised
 *     plain-text template. Converted by a safety net (escape <>, autolink URLs into
 *     friendly anchors, newline -> <br>) so a plain-text body can never render as a
 *     collapsed wall of text once the email channel sends as HTML.
 *
 * Data placeholder values are HTML-escaped upstream in
 * NotificationTemplateService::buildPlaceholders() when the channel is email, so this
 * class does not re-escape substituted values — it only escapes the literal template
 * text it receives.
 *
 * SMS and WhatsApp never pass through this renderer.
 */
class EmailBodyRenderer
{
    /**
     * Friendly anchor labels keyed by a substring matched against the URL.
     * Order matters — first match wins (most specific patterns first).
     *
     * @var array<string, string>
     */
    private const LINK_LABELS = [
        'maps.google'        => 'Open in Google Maps',
        'google.com/maps'    => 'Open in Google Maps',
        '/maps/'             => 'Open in Google Maps',
        'waze.'              => 'Open in Waze',
        'calendar.google'    => 'Add to calendar',
        '/appointments/edit' => 'Edit appointment',
        '/appointments'      => 'View appointment',
        '#terms'             => 'Terms',
        '#privacy'           => 'Privacy',
        '/r/'                => 'Manage appointment',
        '/manage'            => 'Manage appointment',
        '/booking'           => 'Booking page',
    ];

    /**
     * Render the full responsive HTML email document for a notification body.
     */
    public function render(string $body, string $subject = '', string $preheader = ''): string
    {
        $contentHtml = self::isHtmlBody($body)
            ? $body
            : $this->plainTextToHtml($body);

        return (string) view('emails/notification', [
            'contentHtml' => $contentHtml,
            'subject'     => $subject,
            'preheader'   => $preheader,
        ]);
    }

    /**
     * Derive a plain-text alternative (multipart alt body) from a rendered body.
     * HTML is flattened to readable text; plain text is returned unchanged.
     */
    public function toPlainText(string $body): string
    {
        if (!self::isHtmlBody($body)) {
            return $body;
        }

        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $body) ?? $body;
        $text = preg_replace('/<\/(p|div|tr|h[1-6]|li)>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        // Collapse runs of 3+ newlines and trailing spaces.
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Heuristic: does the body contain block/inline HTML produced by a redesigned
     * template? (Plain-text templates contain no tags.)
     *
     * Shared with NotificationTemplateService so the channel-aware block builders and
     * this renderer agree on whether a template is HTML — preventing mixed
     * plain-text/HTML bodies that would collapse on send.
     */
    public static function isHtmlBody(string $body): bool
    {
        return (bool) preg_match(
            '/<(?:a|p|div|table|tr|td|h[1-6]|ul|ol|li|br|span|strong|em|img)\b[^>]*>/i',
            $body
        );
    }

    /**
     * Safety net: convert a plain-text body into shell-ready HTML.
     * Escapes angle brackets, turns bare URLs into friendly anchors, and preserves
     * line breaks. The '&' is left untouched because upstream escaping already
     * produced '&amp;' in any substituted values.
     */
    private function plainTextToHtml(string $body): string
    {
        $escaped = str_replace(['<', '>'], ['&lt;', '&gt;'], $body);

        $linked = preg_replace_callback(
            '/(https?:\/\/[^\s<]+|mailto:[^\s<]+)/i',
            function (array $m): string {
                $raw   = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
                $href  = esc($raw);
                $label = esc($this->friendlyLinkLabel($raw));

                return '<a href="' . $href . '" class="btn btn-secondary">' . $label . '</a>';
            },
            $escaped
        ) ?? $escaped;

        return nl2br($linked, false);
    }

    /**
     * Map a URL to a human-friendly anchor label.
     */
    private function friendlyLinkLabel(string $url): string
    {
        if (stripos($url, 'mailto:') === 0) {
            return substr($url, 7);
        }

        $haystack = strtolower($url);
        foreach (self::LINK_LABELS as $needle => $label) {
            if (str_contains($haystack, $needle)) {
                return $label;
            }
        }

        return 'Open link';
    }
}
