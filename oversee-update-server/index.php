<?php
/**
 * Oversee Plugin Update Server
 * Version: 2.0.0
 *
 * Production-grade update server with proper beta/stable channel separation.
 * Pulls releases dynamically from GitHub.
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

$CONFIG = [
    // GitHub
    'github_user' => 'spoonvash',
    'github_repo' => 'Oversee-Agency-Plugin',

    // Beta testers get pre-releases (domain list)
    'beta_testers' => [
        'overseeagency.com',
        'www.overseeagency.com',
        'developer.overseeagency.com',
        'test.overseeagency.com',
        'staging.overseeagency.com',
        'dev.overseeagency.com',
        'localhost',
    ],

    // Cache TTL in seconds (5 minutes)
    'cache_ttl' => 300,

    // Forced rollback - uncomment to force all sites to downgrade
    // 'force_rollback' => [
    //     'affected_versions' => ['2.3.1', '2.3.1-beta'],
    //     'safe_version' => '2.3.0',
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
header('X-Update-Server-Version: 2.0.0');

define('CACHE_DIR', __DIR__ . '/cache');
define('LOGS_DIR', __DIR__ . '/logs');

// Ensure directories exist
foreach ([CACHE_DIR, LOGS_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
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
    case 'status':
        handleStatus($CONFIG);
        break;
    case 'clear-cache':
        handleClearCache();
        break;
    default:
        respond(['error' => 'Invalid action', 'valid_actions' => ['update-check', 'plugin-info', 'status', 'clear-cache']]);
}

// ============================================================================
// UPDATE CHECK - Core update logic
// ============================================================================

function handleUpdateCheck($config, $plugin) {
    $slug = $_REQUEST['slug'] ?? '';
    $installed_version = $_REQUEST['version'] ?? '0.0.0';
    $site_url = $_REQUEST['site_url'] ?? '';

    // Validate plugin slug
    if ($slug !== $plugin['slug']) {
        respond(['error' => 'Unknown plugin', 'requested_slug' => $slug]);
        return;
    }

    // Check for forced rollback first
    if (isset($config['force_rollback'])) {
        $rollback = $config['force_rollback'];
        if (in_array($installed_version, $rollback['affected_versions'])) {
            $release = fetchGitHubRelease($config, $rollback['safe_version']);
            if ($release) {
                respond([
                    'update_available' => true,
                    'version' => $rollback['safe_version'],
                    'download_url' => $release['download_url'],
                    'tested' => $plugin['tested'],
                    'requires_php' => $plugin['requires_php'],
                    'emergency' => true,
                    'upgrade_notice' => 'CRITICAL: ' . $rollback['reason']
                ]);
                return;
            }
        }
    }

    // Determine channel based on site
    $is_beta_tester = isBetaTester($site_url, $config);

    // Get appropriate release for this channel
    $release = getLatestRelease($config, $is_beta_tester);

    if (!$release) {
        respond(['update_available' => false, 'reason' => 'No releases found']);
        return;
    }

    // Compare versions properly (handles -beta suffix)
    $update_available = version_compare(
        normalizeVersion($installed_version),
        normalizeVersion($release['version']),
        '<'
    );

    if ($update_available) {
        writeLog("Update available: {$installed_version} -> {$release['version']} for " . parse_url($site_url, PHP_URL_HOST));
        respond([
            'update_available' => true,
            'version' => $release['version'],
            'download_url' => $release['download_url'],
            'tested' => $plugin['tested'],
            'requires_php' => $plugin['requires_php'],
            'channel' => $is_beta_tester ? 'beta' : 'stable',
            'is_beta' => $release['is_beta']
        ]);
    } else {
        respond([
            'update_available' => false,
            'installed' => $installed_version,
            'latest' => $release['version'],
            'channel' => $is_beta_tester ? 'beta' : 'stable'
        ]);
    }
}

// ============================================================================
// PLUGIN INFO - WordPress plugin details popup
// ============================================================================

function handlePluginInfo($config, $plugin) {
    $site_url = $_REQUEST['site_url'] ?? '';
    $is_beta_tester = isBetaTester($site_url, $config);

    $release = getLatestRelease($config, $is_beta_tester);
    $all_releases = getAllReleases($config);

    respond([
        'name' => $plugin['name'],
        'slug' => $plugin['slug'],
        'version' => $release['version'] ?? '0.0.0',
        'author' => $plugin['author'],
        'requires' => $plugin['requires'],
        'tested' => $plugin['tested'],
        'requires_php' => $plugin['requires_php'],
        'homepage' => $plugin['homepage'],
        'download_link' => $release['download_url'] ?? '',
        'banners' => $plugin['banners'],
        'icons' => $plugin['icons'],
        'sections' => [
            'description' => $plugin['description'],
            'changelog' => buildChangelog($all_releases)
        ],
        'last_updated' => $release['date'] ?? date('Y-m-d')
    ]);
}

// ============================================================================
// STATUS - Health check and debugging
// ============================================================================

function handleStatus($config) {
    $releases = getAllReleases($config);

    // Find latest stable and beta
    $stable = null;
    $beta = null;

    foreach ($releases as $release) {
        if ($release['is_beta']) {
            if (!$beta || version_compare(normalizeVersion($release['version']), normalizeVersion($beta['version']), '>')) {
                $beta = $release;
            }
        } else {
            if (!$stable || version_compare(normalizeVersion($release['version']), normalizeVersion($stable['version']), '>')) {
                $stable = $release;
            }
        }
    }

    // Beta channel should show the higher of beta or stable
    $beta_channel_version = $beta;
    if ($stable && (!$beta || version_compare(normalizeVersion($stable['version']), normalizeVersion($beta['version']), '>'))) {
        $beta_channel_version = $stable;
    }

    respond([
        'status' => 'ok',
        'server_version' => '2.0.0',
        'server_time' => date('c'),
        'stable_version' => $stable['version'] ?? 'none',
        'beta_version' => $beta_channel_version['version'] ?? 'none',
        'total_releases' => count($releases),
        'last_fetched' => getCacheTime(),
        'cache_ttl' => $config['cache_ttl'] . ' seconds',
        'beta_testers' => $config['beta_testers']
    ]);
}

// ============================================================================
// CACHE MANAGEMENT
// ============================================================================

function handleClearCache() {
    $files = glob(CACHE_DIR . '/*.json');
    foreach ($files as $file) {
        unlink($file);
    }
    writeLog("Cache cleared manually");
    respond(['status' => 'ok', 'message' => 'Cache cleared', 'files_removed' => count($files)]);
}

// ============================================================================
// GITHUB INTEGRATION - Dynamic release fetching
// ============================================================================

function getAllReleases($config) {
    $cache_file = CACHE_DIR . '/releases.json';
    $cache_ttl = $config['cache_ttl'];

    // Check cache
    if (file_exists($cache_file)) {
        $cache_age = time() - filemtime($cache_file);
        if ($cache_age < $cache_ttl) {
            $cached = json_decode(file_get_contents($cache_file), true);
            if ($cached) return $cached;
        }
    }

    // Fetch from GitHub
    $url = "https://api.github.com/repos/{$config['github_user']}/{$config['github_repo']}/releases";

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Oversee-Update-Server/2.0',
                'Accept: application/vnd.github.v3+json'
            ],
            'timeout' => 10
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if (!$response) {
        writeLog("Failed to fetch releases from GitHub");
        // Return cached data even if stale
        if (file_exists($cache_file)) {
            return json_decode(file_get_contents($cache_file), true) ?: [];
        }
        return [];
    }

    $github_releases = json_decode($response, true);

    if (!is_array($github_releases)) {
        writeLog("Invalid response from GitHub");
        return [];
    }

    $releases = [];

    foreach ($github_releases as $gh_release) {
        // Skip drafts
        if ($gh_release['draft']) continue;

        $tag = $gh_release['tag_name'];
        $version = ltrim($tag, 'v');
        $is_beta = $gh_release['prerelease'] || strpos($version, 'beta') !== false || strpos($version, 'alpha') !== false || strpos($version, 'rc') !== false;

        // Find the plugin zip asset
        $download_url = null;
        foreach ($gh_release['assets'] ?? [] as $asset) {
            if ($asset['name'] === 'oversee-helpdesk.zip') {
                $download_url = $asset['browser_download_url'];
                break;
            }
        }

        if (!$download_url) continue; // Skip releases without plugin zip

        $releases[] = [
            'version' => $version,
            'tag' => $tag,
            'is_beta' => $is_beta,
            'download_url' => $download_url,
            'date' => date('Y-m-d', strtotime($gh_release['published_at'])),
            'changelog' => $gh_release['body'] ?: 'Bug fixes and improvements'
        ];
    }

    // Sort by version (newest first)
    usort($releases, function($a, $b) {
        return version_compare(normalizeVersion($b['version']), normalizeVersion($a['version']));
    });

    // Cache the results
    file_put_contents($cache_file, json_encode($releases, JSON_PRETTY_PRINT));
    writeLog("Fetched " . count($releases) . " releases from GitHub");

    return $releases;
}

function getLatestRelease($config, $include_beta = false) {
    $releases = getAllReleases($config);

    if (empty($releases)) {
        return null;
    }

    // If beta tester, find the highest version (stable or beta)
    if ($include_beta) {
        return $releases[0]; // Already sorted by version, newest first
    }

    // For stable channel, find highest non-beta version
    foreach ($releases as $release) {
        if (!$release['is_beta']) {
            return $release;
        }
    }

    // Fallback: if no stable releases, return null (don't give beta to stable users)
    return null;
}

function fetchGitHubRelease($config, $version) {
    $releases = getAllReleases($config);

    foreach ($releases as $release) {
        if ($release['version'] === $version) {
            return $release;
        }
    }

    return null;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Normalize version for comparison
 * Converts "2.3.1-beta" to "2.3.1.0" and "2.3.1" to "2.3.1.1"
 * This ensures stable versions are always higher than their beta counterparts
 */
function normalizeVersion($version) {
    // Remove 'v' prefix if present
    $version = ltrim($version, 'v');

    // Check if it's a beta/pre-release
    $is_prerelease = preg_match('/-(beta|alpha|rc|dev)/i', $version);

    // Remove the suffix for base comparison
    $base = preg_replace('/-(beta|alpha|rc|dev).*/i', '', $version);

    // Ensure we have at least 3 parts
    $parts = explode('.', $base);
    while (count($parts) < 3) {
        $parts[] = '0';
    }

    // Add a 4th part: 0 for pre-release, 1 for stable
    // This ensures 2.3.1 > 2.3.1-beta
    $parts[] = $is_prerelease ? '0' : '1';

    return implode('.', $parts);
}

function isBetaTester($site_url, $config) {
    if (empty($site_url)) return false;

    $domain = parse_url($site_url, PHP_URL_HOST);
    if (!$domain) return false;

    // Remove www. for comparison
    $domain = preg_replace('/^www\./', '', $domain);

    foreach ($config['beta_testers'] as $beta_domain) {
        $beta_domain = preg_replace('/^www\./', '', $beta_domain);
        if ($domain === $beta_domain) {
            return true;
        }
    }

    return false;
}

function buildChangelog($releases) {
    $html = '';

    // Show last 10 releases
    $releases = array_slice($releases, 0, 10);

    foreach ($releases as $release) {
        $badge = $release['is_beta'] ? ' <span style="background:#f59e0b;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">BETA</span>' : '';
        $html .= '<h4>' . htmlspecialchars($release['version']) . $badge;
        if (!empty($release['date'])) {
            $html .= ' <small style="color:#666;">(' . htmlspecialchars($release['date']) . ')</small>';
        }
        $html .= '</h4>';
        $html .= '<div style="margin-bottom:15px;">' . nl2br(htmlspecialchars($release['changelog'])) . '</div>';
    }

    return $html ?: '<p>Initial release</p>';
}

function getCacheTime() {
    $cache_file = CACHE_DIR . '/releases.json';
    if (file_exists($cache_file)) {
        return date('c', filemtime($cache_file));
    }
    return 'never';
}

function respond($data) {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function writeLog($message) {
    $file = LOGS_DIR . '/update-server-' . date('Y-m') . '.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents($file, $line, FILE_APPEND);
}
