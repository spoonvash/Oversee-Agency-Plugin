<?php
/**
 * Simple Plugin Update Server
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$slug = isset($_REQUEST['slug']) ? $_REQUEST['slug'] : '';
$version = isset($_REQUEST['version']) ? $_REQUEST['version'] : '';

$plugins = [
    'oversee-helpdesk' => [
        'version' => '2.1.3',
        'download_url' => 'https://overseeagency.com/wp-content/plugins/oversee-license-server/oversee-helpdesk.zip',
        'name' => 'Oversee Helpdesk',
        'author' => 'Oversee Agency',
        'requires' => '5.8',
        'tested' => '6.4',
        'requires_php' => '7.4',
    ]
];

if ($action === 'update-check' && isset($plugins[$slug])) {
    $plugin = $plugins[$slug];
    if (version_compare($version, $plugin['version'], '<')) {
        echo json_encode(['update_available' => true, 'version' => $plugin['version'], 'download_url' => $plugin['download_url']]);
    } else {
        echo json_encode(['update_available' => false]);
    }
    exit;
}

if ($action === 'plugin-info' && isset($plugins[$slug])) {
    $plugin = $plugins[$slug];
    echo json_encode([
        'name' => $plugin['name'],
        'slug' => $slug,
        'version' => $plugin['version'],
        'author' => $plugin['author'],
        'requires' => $plugin['requires'],
        'tested' => $plugin['tested'],
        'requires_php' => $plugin['requires_php'],
        'download_link' => $plugin['download_url'],
        'sections' => ['changelog' => '<p>Version ' . $plugin['version'] . ' - Bug fixes</p>']
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
