<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Beta tester domains (get pre-releases)
$BETA_TESTERS = [
    'overseeagency.com',
    'www.overseeagency.com'
];

// GitHub repo info
$GITHUB_USER = 'spoonvash';
$GITHUB_REPO = 'Oversee-Agency-Plugin';

// Fetch latest releases from GitHub
function getLatestVersions($user, $repo) {
    $url = "https://api.github.com/repos/{$user}/{$repo}/releases";
    $opts = ['http' => ['header' => "User-Agent: WordPress-Plugin-Updater\r\n"]];
    $releases = @json_decode(file_get_contents($url, false, stream_context_create($opts)), true);
    
    $stable = null;
    $beta = null;
    
    if ($releases) {
        foreach ($releases as $release) {
            $version = ltrim($release['tag_name'], 'v');
            $zip_url = null;
            
            foreach ($release['assets'] as $asset) {
                if ($asset['name'] === 'oversee-helpdesk.zip') {
                    $zip_url = $asset['browser_download_url'];
                    break;
                }
            }
            
            if (!$zip_url) continue;
            
            if (!$release['prerelease'] && !$stable) {
                $stable = ['version' => $version, 'url' => $zip_url];
            }
            if (!$beta) {
                $beta = ['version' => str_replace('-beta', '', $version), 'url' => $zip_url];
            }
            
            if ($stable && $beta) break;
        }
    }
    
    return [
        'stable' => $stable ?: ['version' => '2.1.5', 'url' => ''],
        'beta' => $beta ?: ['version' => '2.1.5', 'url' => '']
    ];
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$slug = isset($_REQUEST['slug']) ? $_REQUEST['slug'] : '';
$version = isset($_REQUEST['version']) ? $_REQUEST['version'] : '';
$site_url = isset($_REQUEST['site_url']) ? $_REQUEST['site_url'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');

// Get versions from GitHub
$versions = getLatestVersions($GITHUB_USER, $GITHUB_REPO);

// Determine which version to serve
$requesting_domain = parse_url($site_url, PHP_URL_HOST);
$is_beta_tester = in_array($requesting_domain, $BETA_TESTERS);
$release = $is_beta_tester ? $versions['beta'] : $versions['stable'];

$plugins = [
    'oversee-helpdesk' => [
        'version' => $release['version'],
        'download_url' => $release['url'],
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
