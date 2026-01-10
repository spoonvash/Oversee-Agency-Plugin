<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Beta tester domains
$BETA_TESTERS = [
    'overseeagency.com',
    'www.overseeagency.com'
];

// Fetch current versions from manifest
$manifest_url = 'https://overseeagency.com/wp-content/uploads/plugins/version.json';
$manifest = @json_decode(file_get_contents($manifest_url), true);
$STABLE_VERSION = $manifest['stable'] ?? '2.1.5';
$BETA_VERSION = $manifest['beta'] ?? '2.1.5';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$slug = isset($_REQUEST['slug']) ? $_REQUEST['slug'] : '';
$version = isset($_REQUEST['version']) ? $_REQUEST['version'] : '';
$site_url = isset($_REQUEST['site_url']) ? $_REQUEST['site_url'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');

// Determine which version to serve
$requesting_domain = parse_url($site_url, PHP_URL_HOST);
$is_beta_tester = in_array($requesting_domain, $BETA_TESTERS);
$serve_version = $is_beta_tester ? $BETA_VERSION : $STABLE_VERSION;

$plugins = [
    'oversee-helpdesk' => [
        'version' => $serve_version,
        'download_url' => 'https://overseeagency.com/wp-content/uploads/plugins/oversee-helpdesk.zip',
        'name' => 'Oversee Helpdesk',
        'author' => '<a href="https://overseeagency.com">Oversee Agency</a>',
        'requires' => '5.8',
        'tested' => '6.7',
        'requires_php' => '7.4',
        'homepage' => 'https://overseeagency.com/plugins/oversee-helpdesk',
        'banner_low' => 'https://overseeagency.com/wp-content/plugin-assets/banner-772x250.png',
        'banner_high' => 'https://overseeagency.com/wp-content/plugin-assets/banner-1544x500.png',
        'icon_1x' => 'https://overseeagency.com/wp-content/plugin-assets/icon-128x128.png',
        'icon_2x' => 'https://overseeagency.com/wp-content/plugin-assets/icon-256x256.png'
    ]
];

if ($action === 'update-check' && isset($plugins[$slug])) {
    $p = $plugins[$slug];
    if (version_compare($version, $p['version'], '<')) {
        echo json_encode(['update_available' => true, 'version' => $p['version'], 'download_url' => $p['download_url'], 'tested' => $p['tested']]);
    } else {
        echo json_encode(['update_available' => false]);
    }
    exit;
}

if ($action === 'plugin-info' && isset($plugins[$slug])) {
    $p = $plugins[$slug];
    echo json_encode([
        'name' => $p['name'],
        'slug' => $slug,
        'version' => $p['version'],
        'author' => $p['author'],
        'requires' => $p['requires'],
        'tested' => $p['tested'],
        'requires_php' => $p['requires_php'],
        'homepage' => $p['homepage'],
        'download_link' => $p['download_url'],
        'banners' => ['low' => $p['banner_low'], 'high' => $p['banner_high']],
        'icons' => ['1x' => $p['icon_1x'], '2x' => $p['icon_2x']],
        'sections' => [
            'description' => '<p>Professional helpdesk and support ticket system with integrated knowledge base.</p>',
            'changelog' => '<h4>' . $p['version'] . '</h4><ul><li>Latest updates and fixes</li></ul>'
        ]
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
