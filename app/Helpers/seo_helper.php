<?php

/**
 * SEO Helper
 *
 * Renders server-side SEO meta tags for public-facing pages.
 * All values are escaped at output time.
 *
 * Loaded automatically via Config/Autoload.php $helpers.
 *
 * Usage:
 *   echo seo_meta([
 *       'title'       => 'Book at Acme Salon',
 *       'description' => 'Schedule your appointment online.',
 *       'canonical'   => current_url(),
 *       'image'       => 'https://example.com/og.png',
 *       'robots'      => 'index, follow',
 *       'schema'      => ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', ...],
 *   ]);
 */

if (!function_exists('seo_meta')) {
    /**
     * Build the full SEO <head> meta block.
     *
     * @param array{
     *   title?:       string,
     *   description?: string,
     *   canonical?:   string,
     *   image?:       string,
     *   robots?:      string,
     *   schema?:      array,
     * } $data
     * @return string  Raw HTML — output directly into <head>, do NOT escape.
     */
    function seo_meta(array $data): string
    {
        $title       = $data['title']       ?? 'Book an Appointment';
        $description = $data['description'] ?? '';
        $canonical   = $data['canonical']   ?? current_url();
        $image       = $data['image']       ?? '';
        $robots      = $data['robots']      ?? 'index, follow';
        $schema      = $data['schema']      ?? null;

        $e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html  = '<title>' . $e($title) . '</title>' . "\n";
        $html .= '    <meta name="robots" content="' . $e($robots) . '">' . "\n";

        if ($description !== '') {
            $html .= '    <meta name="description" content="' . $e($description) . '">' . "\n";
        }

        $html .= '    <link rel="canonical" href="' . $e($canonical) . '">' . "\n";

        // Open Graph
        $html .= '    <meta property="og:type" content="website">' . "\n";
        $html .= '    <meta property="og:title" content="' . $e($title) . '">' . "\n";
        $html .= '    <meta property="og:url" content="' . $e($canonical) . '">' . "\n";

        if ($description !== '') {
            $html .= '    <meta property="og:description" content="' . $e($description) . '">' . "\n";
        }

        if ($image !== '') {
            $html .= '    <meta property="og:image" content="' . $e($image) . '">' . "\n";
            $html .= '    <meta property="og:image:width" content="1200">' . "\n";
            $html .= '    <meta property="og:image:height" content="630">' . "\n";
        }

        // Twitter card
        $html .= '    <meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";
        $html .= '    <meta name="twitter:title" content="' . $e($title) . '">' . "\n";

        if ($description !== '') {
            $html .= '    <meta name="twitter:description" content="' . $e($description) . '">' . "\n";
        }

        if ($image !== '') {
            $html .= '    <meta name="twitter:image" content="' . $e($image) . '">' . "\n";
        }

        // JSON-LD structured data
        if (!empty($schema) && is_array($schema)) {
            $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $html .= '    <script type="application/ld+json">' . "\n" . $json . "\n    </script>" . "\n";
        }

        return $html;
    }
}
