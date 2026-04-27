<?php
/**
 * Instruction-media URL validation. We deliberately do NOT accept arbitrary
 * iframe HTML from clients or staff — only allow-listed URL patterns from
 * known providers (Loom, YouTube, Vimeo). The frontend renders the embed
 * itself from the validated URL, so the user-facing surface never echoes
 * untrusted markup.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Instruction_Media {

    const PROVIDERS = ['loom', 'youtube', 'vimeo'];

    /**
     * Returns ['provider' => 'loom|youtube|vimeo', 'url' => https://..., 'embed_url' => https://..., 'id' => '...'] or WP_Error.
     */
    public static function validate_video_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return new WP_Error('ocd_media_empty', 'URL is required.');
        }
        if (!preg_match('#^https://#i', $url)) {
            return new WP_Error('ocd_media_scheme', 'Only https:// URLs are accepted.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return new WP_Error('ocd_media_invalid', 'Invalid URL.');
        }
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';

        // Loom: https://www.loom.com/share/<id>  or  https://loom.com/share/<id>
        if (preg_match('#(^|\.)loom\.com$#', $host)) {
            if (preg_match('#^/share/([A-Za-z0-9]+)/?$#', $path, $m) || preg_match('#^/embed/([A-Za-z0-9]+)/?$#', $path, $m)) {
                $id = $m[1];
                return [
                    'provider'  => 'loom',
                    'url'       => 'https://www.loom.com/share/' . $id,
                    'embed_url' => 'https://www.loom.com/embed/' . $id,
                    'id'        => $id,
                ];
            }
            return new WP_Error('ocd_media_invalid', 'Loom URL must be of the form https://www.loom.com/share/<id>.');
        }

        // YouTube: youtu.be/<id>, youtube.com/watch?v=<id>, youtube.com/embed/<id>
        if (preg_match('#(^|\.)youtube\.com$#', $host) || $host === 'youtu.be' || preg_match('#(^|\.)youtube-nocookie\.com$#', $host)) {
            $id = self::extract_youtube_id($host, $path, $parts['query'] ?? '');
            if (!$id) {
                return new WP_Error('ocd_media_invalid', 'YouTube URL must contain a valid video id.');
            }
            return [
                'provider'  => 'youtube',
                'url'       => 'https://www.youtube.com/watch?v=' . $id,
                'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $id,
                'id'        => $id,
            ];
        }

        // Vimeo: vimeo.com/<id> or player.vimeo.com/video/<id>
        if (preg_match('#(^|\.)vimeo\.com$#', $host)) {
            if (preg_match('#^/(?:video/)?(\d+)/?$#', $path, $m)) {
                $id = $m[1];
                return [
                    'provider'  => 'vimeo',
                    'url'       => 'https://vimeo.com/' . $id,
                    'embed_url' => 'https://player.vimeo.com/video/' . $id,
                    'id'        => $id,
                ];
            }
            return new WP_Error('ocd_media_invalid', 'Vimeo URL must be of the form https://vimeo.com/<id>.');
        }

        return new WP_Error('ocd_media_provider', 'Only Loom, YouTube, and Vimeo URLs are accepted.');
    }

    /**
     * Validates an instruction image URL. Accepts only:
     *   - URLs on the local site uploads directory
     *   - oversee-private served file ids (rest URL form)
     *
     * Untrusted external image hosts are rejected to avoid SSRF/tracking.
     */
    public static function validate_image_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return new WP_Error('ocd_media_empty', 'URL is required.');
        }
        if (!preg_match('#^https://#i', $url)) {
            return new WP_Error('ocd_media_scheme', 'Only https:// URLs are accepted.');
        }

        $home = function_exists('home_url') ? home_url() : '';
        $home_host = '';
        if ($home) {
            $h = parse_url($home);
            $home_host = isset($h['host']) ? strtolower($h['host']) : '';
        }
        $parts = parse_url($url);
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        if ($home_host && $host === $home_host) {
            return ['provider' => 'local', 'url' => $url];
        }
        return new WP_Error('ocd_media_image_host', 'Only images hosted on this site are allowed for instructions.');
    }

    private static function extract_youtube_id($host, $path, $query) {
        if ($host === 'youtu.be') {
            if (preg_match('#^/([A-Za-z0-9_-]{6,32})/?$#', $path, $m)) return $m[1];
            return null;
        }
        if (preg_match('#^/embed/([A-Za-z0-9_-]{6,32})/?$#', $path, $m)) return $m[1];
        if (preg_match('#^/shorts/([A-Za-z0-9_-]{6,32})/?$#', $path, $m)) return $m[1];
        if ($path === '/watch' && $query) {
            parse_str($query, $q);
            if (!empty($q['v']) && preg_match('#^[A-Za-z0-9_-]{6,32}$#', $q['v'])) return $q['v'];
        }
        return null;
    }
}
