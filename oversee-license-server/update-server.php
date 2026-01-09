<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$slug = isset($_REQUEST['slug']) ? $_REQUEST['slug'] : '';
$version = isset($_REQUEST['version']) ? $_REQUEST['version'] : '';

$plugins = [
    'oversee-helpdesk' => [
        'version' => '2.1.4',
        'download_url' => 'https://overseeagency.com/wp-content/uploads/plugins/oversee-helpdesk.zip',
        'name' => 'Oversee Helpdesk',
        'author' => '<a href="https://overseeagency.com">Oversee Agency</a>',
        'requires' => '5.8',
        'tested' => '6.7',
        'requires_php' => '7.4',
        'homepage' => 'https://overseeagency.com/plugins/oversee-helpdesk',
        'banner_low' => 'https://overseeagency.com/wp-content/uploads/plugin-assets/banner-772x250.png',
        'banner_high' => 'https://overseeagency.com/wp-content/uploads/plugin-assets/banner-1544x500.png',
        'icon_1x' => 'https://overseeagency.com/wp-content/uploads/plugin-assets/icon-128x128.png',
        'icon_2x' => 'https://overseeagency.com/wp-content/uploads/plugin-assets/icon-256x256.png'
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
            'description' => '<p>Complete support ticket system with knowledge base and admin dashboard.</p><ul><li>Ticket management</li><li>Knowledge base</li><li>Agent assignment</li><li>Email notifications</li><li>White-label branding</li><li>REST API</li></ul>',
            'changelog' => '<h4>2.1.4</h4><ul><li>Improved update system</li><li>Bug fixes</li></ul><h4>2.1.3</h4><ul><li>Added automatic updates</li></ul>'
        ]
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
