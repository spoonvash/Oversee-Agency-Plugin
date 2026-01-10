<?php
/**
 * Oversee License Server - Admin Release Manager
 * Add this to your WordPress admin
 */

if (!defined('ABSPATH')) exit;

class Oversee_Release_Manager {
    
    private $github_user = 'spoonvash';
    private $github_repo = 'Oversee-Agency-Plugin';
    private $update_server = 'https://overseeagency.com/wp-content/oversee-update-server/';
    private $option_name = 'oversee_release_manager_settings';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_oversee_release_action', [$this, 'handle_ajax']);
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'oversee-license-server',
            'Release Manager',
            'Release Manager',
            'manage_options',
            'oversee-release-manager',
            [$this, 'render_page']
        );
    }
    
    public function register_settings() {
        register_setting($this->option_name, $this->option_name);
    }
    
    public function render_page() {
        $settings = get_option($this->option_name, []);
        $github_token = $settings['github_token'] ?? '';
        
        // Get current status
        $status = $this->get_update_server_status();
        ?>
        <div class="wrap">
            <h1>Oversee Release Manager</h1>
            
            <div class="card" style="max-width: 600px; padding: 20px; margin-bottom: 20px;">
                <h2>Current Status</h2>
                <table class="widefat" style="margin-top: 10px;">
                    <tr><th>Stable Version</th><td><strong style="color: green;"><?php echo esc_html($status['stable_version'] ?? 'Unknown'); ?></strong></td></tr>
                    <tr><th>Beta Version</th><td><strong style="color: orange;"><?php echo esc_html($status['beta_version'] ?? 'Unknown'); ?></strong></td></tr>
                    <tr><th>Total Releases</th><td><?php echo esc_html($status['total_releases'] ?? 'Unknown'); ?></td></tr>
                    <tr><th>Last Fetched</th><td><?php echo esc_html($status['last_fetched'] ?? 'Unknown'); ?></td></tr>
                </table>
                <p style="margin-top: 15px;">
                    <button type="button" class="button" onclick="overseeAction('clear-cache')">Clear Cache</button>
                    <button type="button" class="button" onclick="overseeAction('refresh-status')">Refresh Status</button>
                </p>
            </div>
            
            <div class="card" style="max-width: 600px; padding: 20px; margin-bottom: 20px;">
                <h2>Create New Release</h2>
                <form id="release-form">
                    <table class="form-table">
                        <tr>
                            <th>Version Number</th>
                            <td>
                                <input type="text" id="release-version" class="regular-text" placeholder="e.g., 2.2.1" required>
                                <p class="description">Enter version number without 'v' prefix</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Release Type</th>
                            <td>
                                <label><input type="radio" name="release-type" value="beta" checked> Beta (overseeagency.com only)</label><br>
                                <label><input type="radio" name="release-type" value="stable"> Stable (all sites)</label>
                            </td>
                        </tr>
                        <tr>
                            <th>Release Notes</th>
                            <td>
                                <textarea id="release-notes" class="large-text" rows="4" placeholder="What's new in this release..."></textarea>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="button" class="button button-primary button-hero" onclick="createRelease()">🚀 Create Release</button>
                    </p>
                </form>
            </div>
            
            <div class="card" style="max-width: 600px; padding: 20px; margin-bottom: 20px;">
                <h2>GitHub Settings</h2>
                <form method="post" action="options.php">
                    <?php settings_fields($this->option_name); ?>
                    <table class="form-table">
                        <tr>
                            <th>GitHub Token</th>
                            <td>
                                <input type="password" name="<?php echo $this->option_name; ?>[github_token]" value="<?php echo esc_attr($github_token); ?>" class="regular-text">
                                <p class="description">
                                    <a href="https://github.com/settings/tokens/new?scopes=repo&description=Oversee%20Release%20Manager" target="_blank">Generate a token</a> with 'repo' permissions
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Token'); ?>
                </form>
            </div>
            
            <div class="card" style="max-width: 600px; padding: 20px;">
                <h2>Emergency Rollback</h2>
                <p>Force all sites to downgrade to a specific version:</p>
                <table class="form-table">
                    <tr>
                        <th>Affected Versions</th>
                        <td><input type="text" id="rollback-affected" class="regular-text" placeholder="e.g., 2.2.0, 2.2.1"></td>
                    </tr>
                    <tr>
                        <th>Safe Version</th>
                        <td><input type="text" id="rollback-safe" class="regular-text" placeholder="e.g., 2.1.9"></td>
                    </tr>
                    <tr>
                        <th>Reason</th>
                        <td><input type="text" id="rollback-reason" class="regular-text" placeholder="e.g., Critical bug in checkout"></td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-secondary" style="color: red;" onclick="activateRollback()">⚠️ Activate Rollback</button>
                    <button type="button" class="button" onclick="deactivateRollback()">Clear Rollback</button>
                </p>
            </div>
            
            <div id="oversee-log" style="max-width: 600px; margin-top: 20px; padding: 15px; background: #f0f0f0; display: none;">
                <strong>Log:</strong>
                <pre id="log-content" style="margin-top: 10px; white-space: pre-wrap;"></pre>
            </div>
        </div>
        
        <script>
        function log(msg) {
            document.getElementById('oversee-log').style.display = 'block';
            document.getElementById('log-content').textContent += new Date().toLocaleTimeString() + ' - ' + msg + '\n';
        }
        
        function overseeAction(action) {
            log('Running: ' + action);
            
            fetch('<?php echo $this->update_server; ?>?action=' + action)
                .then(r => r.json())
                .then(data => {
                    log('Result: ' + JSON.stringify(data, null, 2));
                    if (action === 'refresh-status' || action === 'clear-cache') {
                        location.reload();
                    }
                })
                .catch(e => log('Error: ' + e.message));
        }
        
        function createRelease() {
            const version = document.getElementById('release-version').value.trim();
            const type = document.querySelector('input[name="release-type"]:checked').value;
            const notes = document.getElementById('release-notes').value.trim();
            
            if (!version) {
                alert('Please enter a version number');
                return;
            }
            
            if (!confirm('Create ' + type + ' release v' + version + '?')) {
                return;
            }
            
            log('Creating ' + type + ' release v' + version + '...');
            
            jQuery.post(ajaxurl, {
                action: 'oversee_release_action',
                release_action: 'create_release',
                version: version,
                type: type,
                notes: notes,
                _wpnonce: '<?php echo wp_create_nonce('oversee_release'); ?>'
            }, function(response) {
                if (response.success) {
                    log('Success: ' + response.data.message);
                    log('GitHub Action will build the release. Wait 1-2 minutes then refresh.');
                } else {
                    log('Error: ' + response.data);
                }
            });
        }
        
        function activateRollback() {
            const affected = document.getElementById('rollback-affected').value.trim();
            const safe = document.getElementById('rollback-safe').value.trim();
            const reason = document.getElementById('rollback-reason').value.trim();
            
            if (!affected || !safe || !reason) {
                alert('Please fill in all rollback fields');
                return;
            }
            
            if (!confirm('DANGER: This will force all sites running versions ' + affected + ' to downgrade to ' + safe + '. Continue?')) {
                return;
            }
            
            log('Activating rollback...');
            
            jQuery.post(ajaxurl, {
                action: 'oversee_release_action',
                release_action: 'activate_rollback',
                affected: affected,
                safe: safe,
                reason: reason,
                _wpnonce: '<?php echo wp_create_nonce('oversee_release'); ?>'
            }, function(response) {
                log(response.success ? 'Rollback activated' : 'Error: ' + response.data);
            });
        }
        
        function deactivateRollback() {
            if (!confirm('Clear the rollback configuration?')) return;
            
            jQuery.post(ajaxurl, {
                action: 'oversee_release_action',
                release_action: 'deactivate_rollback',
                _wpnonce: '<?php echo wp_create_nonce('oversee_release'); ?>'
            }, function(response) {
                log(response.success ? 'Rollback cleared' : 'Error: ' + response.data);
            });
        }
        </script>
        <?php
    }
    
    public function handle_ajax() {
        check_ajax_referer('oversee_release', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $release_action = $_POST['release_action'] ?? '';
        
        switch ($release_action) {
            case 'create_release':
                $this->create_release();
                break;
            case 'activate_rollback':
                $this->activate_rollback();
                break;
            case 'deactivate_rollback':
                $this->deactivate_rollback();
                break;
            default:
                wp_send_json_error('Unknown action');
        }
    }
    
    private function create_release() {
        $version = sanitize_text_field($_POST['version'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'beta');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        if (!$version) {
            wp_send_json_error('Version required');
        }
        
        $settings = get_option($this->option_name, []);
        $token = $settings['github_token'] ?? '';
        
        if (!$token) {
            wp_send_json_error('GitHub token not configured');
        }
        
        // Create tag name
        $tag = 'v' . $version . ($type === 'beta' ? '-beta' : '');
        
        // Get latest commit SHA from Master branch
        $branch_url = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/git/refs/heads/Master";
        $response = wp_remote_get($branch_url, [
            'headers' => [
                'Authorization' => 'token ' . $token,
                'User-Agent' => 'Oversee-Release-Manager',
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to get branch: ' . $response->get_error_message());
        }
        
        $branch_data = json_decode(wp_remote_retrieve_body($response), true);
        $sha = $branch_data['object']['sha'] ?? null;
        
        if (!$sha) {
            wp_send_json_error('Could not get commit SHA');
        }
        
        // Create the tag reference
        $create_ref_url = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/git/refs";
        $response = wp_remote_post($create_ref_url, [
            'headers' => [
                'Authorization' => 'token ' . $token,
                'User-Agent' => 'Oversee-Release-Manager',
                'Accept' => 'application/vnd.github.v3+json',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'ref' => 'refs/tags/' . $tag,
                'sha' => $sha
            ])
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to create tag: ' . $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 201) {
            wp_send_json_success([
                'message' => "Tag {$tag} created successfully. GitHub Action will build the release.",
                'tag' => $tag
            ]);
        } elseif ($code === 422) {
            wp_send_json_error("Tag {$tag} already exists");
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            wp_send_json_error('GitHub error: ' . ($body['message'] ?? 'Unknown error'));
        }
    }
    
    private function activate_rollback() {
        // This would need to update the update server config
        // For now, provide instructions
        $affected = sanitize_text_field($_POST['affected'] ?? '');
        $safe = sanitize_text_field($_POST['safe'] ?? '');
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        // Store rollback config
        update_option('oversee_rollback_config', [
            'active' => true,
            'affected_versions' => array_map('trim', explode(',', $affected)),
            'safe_version' => $safe,
            'reason' => $reason
        ]);
        
        wp_send_json_success('Rollback config saved. You need to update the update server index.php on 20i to read this config.');
    }
    
    private function deactivate_rollback() {
        delete_option('oversee_rollback_config');
        wp_send_json_success('Rollback config cleared');
    }
    
    private function get_update_server_status() {
        $response = wp_remote_get($this->update_server . '?action=status', [
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        
        return json_decode(wp_remote_retrieve_body($response), true) ?: [];
    }
}

// Initialize
new Oversee_Release_Manager();
