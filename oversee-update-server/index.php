<?php
/**
 * Oversee Plugin Update Server
 * Version: 1.0.0
 * 
 * Upload to: https://overseeagency.com/wp-content/uploads/update-server/
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

$CONFIG = [
    // GitHub
    'github_user' => 'spoonvash',
    'github_repo' => 'Oversee-Agency-Plugin',
    'webhook_secret' => 'CHANGE_ME_TO_RANDOM_STRING', // Generate: bin2hex(random_bytes(32))
    
    // Beta testers get pre-releases
    'beta_testers' => [
        'overseeagency.com',
        'www.overseeagency.com'
    ],
    
    // License validation endpoint (your existing system)
    'license_api' => 'https://overseeagency.com/wp-content/plugins/oversee-license-server/oversee-license-server.php',
    
    // Forced rollback - uncomment and edit to force all sites to downgrade
    // 'force_rollback' => [
    //     'affected_versions' => ['2.1.8', '2.1.9'],
    //     'safe_version' => '2.1.7',
    //     'reason' => 'Critical bug discovered'
    // ]
];

$PLUGIN = [
    'slug' => 'oversee-helpdesk',
    'name' => 'Oversee Helpdesk',
    'author' => '<a href="https://overseeagency.com">Oversee Agency</a>',
    'homepage' => 'https://overseeagency.com/plugins/oversee-helpdesk',
    'requires' => '5.8',
    'tested' => '6.7',
    'requires_php' => '7.4',
    'banners' => [
        'low' => 'https://overseeagency.com/wp-content/plugin-assets/banner-772x250.png',
        'high' => 'https://overseeagency.com/wp-content/plugin-assets/banner-1544x500.png'
    ],
    'icons' => [
        '1x' => 'https://overseeagency.com/wp-content/plugin-assets/icon-128x128.png',
        '2x' => 'https://overseeagency.com/wp-content/plugin-assets/icon-256x256.png'
    ],
    'description' => '<p>Professional helpdesk and support ticket system with integrated knowledge base.</p>
        <ul>
            <li>Ticket management with priorities and statuses</li>
            <li>Knowledge base with categories</li>
            <li>Agent assignment and notifications</li>
            <li>Email notifications</li>
            <li>White-label branding</li>
            <li>REST API</li>
        </ul>'
];

// ============================================================================
// MAIN HANDLER
// ============================================================================

error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('CACHE_DIR', __DIR__ . '/cache');
define('PACKAGES_DIR', __DIR__ . '/packages');
define('LOGS_DIR', __DIR__ . '/logs');
define('CACHE_TTL', 300);

// Ensure directories exist
foreach ([CACHE_DIR, PACKAGES_DIR, LOGS_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// Handle GitHub webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_GITHUB_EVENT'])) {
    handleWebhook($CONFIG);
    exit;
}

// Handle API requests
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'update-check':
        handleUpdateCheck($CONFIG, $PLUGIN);
        break;
    case 'plugin-info':
    case 'get_metadata':
        handlePluginInfo($CONFIG, $PLUGIN);
        break;
    case 'download':
        handleDownload($CONFIG, $PLUGIN);
        break;
    case 'status':
        handleStatus($CONFIG);
        break;
    default:
        respond(['error' => 'Invalid action', 'valid_actions' => ['update-check', 'plugin-info', 'download', 'status']]);
}

// ============================================================================
// WEBHOOK HANDLER
// ============================================================================

function handleWebhook($config) {
    $payload = file_get_contents('php://input');
    $event = $_SERVER['HTTP_X_GITHUB_EVENT'];
    
    // Verify signature
    if ($config['webhook_secret'] !== 'CHANGE_ME_TO_RANDOM_STRING') {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $config['webhook_secret']);
        if (!hash_equals($expected, $signature)) {
            http_response_code(401);
            respond(['error' => 'Invalid signature']);
            return;
        }
    }
    
    if ($event !== 'release') {
        respond(['status' => 'ignored', 'event' => $event]);
        return;
    }
    
    $data = json_decode($payload, true);
    
    if ($data['action'] !== 'published') {
        respond(['status' => 'ignored', 'action' => $data['action']]);
        return;
    }
    
    $release = $data['release'];
    $version = ltrim($release['tag_name'], 'v');
    $is_prerelease = $release['prerelease'] || strpos($version, 'beta') !== false;
    
    // Find zip asset
    $zip_url = null;
    foreach ($release['assets'] ?? [] as $asset) {
        if (strpos($asset['name'], '.zip') !== false) {
            $zip_url = $asset['browser_download_url'];
            break;
        }
    }
    
    if (!$zip_url) {
        respond(['error' => 'No zip asset found']);
        return;
    }
    
    // Download the zip
    $zip_content = file_get_contents($zip_url);
    if (!$zip_content) {
        respond(['error' => 'Failed to download zip']);
        return;
    }
    
    $slug = 'oversee-helpdesk';
    $filename = "{$slug}.zip";
    file_put_contents(PACKAGES_DIR . "/{$filename}", $zip_content);
    
    // Update release manifest
    $manifest = getManifest();
    $clean_version = str_replace('-beta', '', $version);
    
    $release_info = [
        'version' => $clean_version,
        'tag' => $release['tag_name'],
        'date' => date('Y-m-d', strtotime($release['published_at'])),
        'changelog' => $release['body'] ?: 'Bug fixes and improvements',
        'prerelease' => $is_prerelease
    ];
    
    if ($is_prerelease) {
        $manifest['beta'] = $release_info;
    } else {
        $manifest['stable'] = $release_info;
        // Stable also becomes beta if it's newer
        if (!isset($manifest['beta']) || version_compare($clean_version, $manifest['beta']['version'], '>')) {
            $manifest['beta'] = $release_info;
        }
    }
    
    $manifest['all_releases'][] = $release_info;
    $manifest['updated'] = date('c');
    
    saveManifest($manifest);
    
    // Clear cache
    array_map('unlink', glob(CACHE_DIR . '/*.json'));
    
    writeLog("Release deployed: v{$version} (" . ($is_prerelease ? 'beta' : 'stable') . ")");
    
    respond([
        'status' => 'success',
        'version' => $version,
        'channel' => $is_prerelease ? 'beta' : 'stable'
    ]);
}

// ============================================================================
// UPDATE CHECK
// ============================================================================

function handleUpdateCheck($config, $plugin) {
    $slug = $_REQUEST['slug'] ?? '';
    $version = $_REQUEST['version'] ?? '0.0.0';
    $site_url = $_REQUEST['site_url'] ?? '';
    $license_key = $_REQUEST['license_key'] ?? '';
    
    if ($slug !== $plugin['slug']) {
        respond(['error' => 'Unknown plugin']);
        return;
    }
    
    // Check for forced rollback first
    if (isset($config['force_rollback'])) {
        $rollback = $config['force_rollback'];
        if (in_array($version, $rollback['affected_versions'])) {
            respond([
                'update_available' => true,
                'version' => $rollback['safe_version'],
                'download_url' => getSignedDownloadUrl($plugin['slug'], $rollback['safe_version'], $license_key, $config),
                'tested' => $plugin['tested'],
                'requires_php' => $plugin['requires_php'],
                'emergency' => true,
                'upgrade_notice' => 'CRITICAL: ' . $rollback['reason']
            ]);
            return;
        }
    }
    
    $release = getRelease($site_url, $config);
    
    if (version_compare($version, $release['version'], '<')) {
        respond([
            'update_available' => true,
            'version' => $release['version'],
            'download_url' => getSignedDownloadUrl($plugin['slug'], $release['version'], $license_key, $config),
            'tested' => $plugin['tested'],
            'requires_php' => $plugin['requires_php']
        ]);
    } else {
        respond(['update_available' => false]);
    }
}

// ============================================================================
// PLUGIN INFO
// ============================================================================

function handlePluginInfo($config, $plugin) {
    $site_url = $_REQUEST['site_url'] ?? '';
    $license_key = $_REQUEST['license_key'] ?? '';
    
    $release = getRelease($site_url, $config);
    $manifest = getManifest();
    
    respond([
        'name' => $plugin['name'],
        'slug' => $plugin['slug'],
        'version' => $release['version'],
        'author' => $plugin['author'],
        'requires' => $plugin['requires'],
        'tested' => $plugin['tested'],
        'requires_php' => $plugin['requires_php'],
        'homepage' => $plugin['homepage'],
        'download_link' => getSignedDownloadUrl($plugin['slug'], $release['version'], $license_key, $config),
        'banners' => $plugin['banners'],
        'icons' => $plugin['icons'],
        'sections' => [
            'description' => $plugin['description'],
            'changelog' => buildChangelog($manifest)
        ],
        'last_updated' => $release['date'] ?? date('Y-m-d')
    ]);
}

// ============================================================================
// DOWNLOAD HANDLER
// ============================================================================

function handleDownload($config, $plugin) {
    $slug = $_REQUEST['slug'] ?? '';
    $version = $_REQUEST['v'] ?? '';
    $expires = $_REQUEST['expires'] ?? 0;
    $license = $_REQUEST['license'] ?? '';
    $sig = $_REQUEST['sig'] ?? '';
    
    // Verify signature
    $expected = hash_hmac('sha256', "{$slug}|{$version}|{$expires}|{$license}", $config['webhook_secret']);
    if (!hash_equals($expected, $sig)) {
        http_response_code(403);
        respond(['error' => 'Invalid download signature']);
        return;
    }
    
    // Check expiry
    if (time() > (int)$expires) {
        http_response_code(403);
        respond(['error' => 'Download link expired']);
        return;
    }
    
    // Validate license (optional - comment out to skip)
    if (!empty($license) && !validateLicense($license, $config)) {
        http_response_code(403);
        respond(['error' => 'Invalid or expired license']);
        return;
    }
    
    // Find the package
    $file = PACKAGES_DIR . "/{$slug}.zip";
    
    if (!file_exists($file)) {
        http_response_code(404);
        respond(['error' => 'Package not found']);
        return;
    }
    
    writeLog("Download: {$slug} v{$version} | License: " . substr($license, 0, 8) . '...');
    
    // Serve file
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $slug . '.zip"');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: no-cache');
    readfile($file);
    exit;
}

// ============================================================================
// STATUS ENDPOINT
// ============================================================================

function handleStatus($config) {
    $manifest = getManifest();
    
    respond([
        'status' => 'ok',
        'server_time' => date('c'),
        'stable_version' => $manifest['stable']['version'] ?? 'none',
        'beta_version' => $manifest['beta']['version'] ?? 'none',
        'last_updated' => $manifest['updated'] ?? 'never',
        'package_exists' => file_exists(PACKAGES_DIR . '/oversee-helpdesk.zip')
    ]);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function getRelease($site_url, $config) {
    $manifest = getManifest();
    
    $domain = parse_url($site_url, PHP_URL_HOST);
    $is_beta = in_array($domain, $config['beta_testers']);
    
    $channel = $is_beta ? 'beta' : 'stable';
    
    if (isset($manifest[$channel])) {
        return $manifest[$channel];
    }
    
    // Fallback
    return $manifest['stable'] ?? $manifest['beta'] ?? ['version' => '0.0.0', 'date' => date('Y-m-d')];
}

function getManifest() {
    $file = CACHE_DIR . '/manifest.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: [];
    }
    return [];
}

function saveManifest($data) {
    file_put_contents(CACHE_DIR . '/manifest.json', json_encode($data, JSON_PRETTY_PRINT));
}

function getSignedDownloadUrl($slug, $version, $license, $config) {
    $expires = time() + 3600; // 1 hour
    $sig = hash_hmac('sha256', "{$slug}|{$version}|{$expires}|{$license}", $config['webhook_secret']);
    
    $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    return $base_url . '?' . http_build_query([
        'action' => 'download',
        'slug' => $slug,
        'v' => $version,
        'expires' => $expires,
        'license' => $license,
        'sig' => $sig
    ]);
}

function validateLicense($license_key, $config) {
    if (empty($license_key)) return true; // Allow updates without license for now
    
    // Call your existing license server
    $response = @file_get_contents($config['license_api'] . '?' . http_build_query([
        'action' => 'validate',
        'license_key' => $license_key
    ]));
    
    if (!$response) return true; // Fail open if license server is down
    
    $data = json_decode($response, true);
    return isset($data['valid']) && $data['valid'];
}

function buildChangelog($manifest) {
    $html = '';
    $releases = $manifest['all_releases'] ?? [];
    
    // Show last 10 releases
    $releases = array_slice($releases, -10);
    $releases = array_reverse($releases);
    
    foreach ($releases as $release) {
        $html .= '<h4>' . htmlspecialchars($release['version']);
        if (!empty($release['date'])) {
            $html .= ' <small>(' . htmlspecialchars($release['date']) . ')</small>';
        }
        $html .= '</h4>';
        $html .= '<p>' . nl2br(htmlspecialchars($release['changelog'])) . '</p>';
    }
    
    return $html ?: '<p>Initial release</p>';
}

function respond($data) {
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function writeLog($message) {
    $file = LOGS_DIR . '/update-server-' . date('Y-m') . '.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    file_put_contents($file, $line, FILE_APPEND);
}
