<?php
/**
 * Project files: per-project, per-task, stage-based file storage with
 * approval workflow, visibility flags, version tracking, and PHP-served
 * downloads from a plugin-private uploads directory (not WP Media).
 *
 * Security model:
 *   - Files are stored under wp-content/uploads/oversee-private/ behind a
 *     deny-all .htaccess and an empty index.html. Direct URLs are never
 *     handed back to clients.
 *   - All access goes through OCD_REST_API::get_file() which verifies the
 *     requesting user owns the project (or is admin) before streaming the
 *     bytes via PHP.
 *   - MIME type is validated server-side against an allow-list. Filename
 *     is sanitized; the on-disk name is randomized to defeat path tricks.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Project_Files {

    const FOLDERS         = ['intake', 'working', 'deliverables', 'archive', 'messages', 'tasks'];
    const VISIBILITIES    = ['client', 'internal'];
    const APPROVAL_STATES = ['pending', 'approved', 'rejected', 'not_required'];

    // 25 MB cap; deliberately small enough that abuse is bounded.
    const MAX_BYTES = 26214400;

    const ALLOWED_MIME = [
        'image/png'         => 'png',
        'image/jpeg'        => 'jpg',
        'image/gif'         => 'gif',
        'image/webp'        => 'webp',
        'image/svg+xml'     => 'svg',
        'application/pdf'   => 'pdf',
        'application/zip'   => 'zip',
        'application/msword'                                                          => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'     => 'docx',
        'application/vnd.ms-excel'                                                    => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'           => 'xlsx',
        'application/vnd.ms-powerpoint'                                               => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'   => 'pptx',
        'text/plain'        => 'txt',
        'text/csv'          => 'csv',
        'text/markdown'     => 'md',
        'audio/mpeg'        => 'mp3',
        'audio/wav'         => 'wav',
        'video/mp4'         => 'mp4',
        'video/quicktime'   => 'mov',
    ];

    /* ---------------- Storage directory ---------------- */

    public static function storage_root() {
        if (function_exists('wp_upload_dir')) {
            $u = wp_upload_dir();
            $base = $u['basedir'] ?? (WP_CONTENT_DIR . '/uploads');
        } else {
            $base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/uploads' : sys_get_temp_dir();
        }
        return rtrim($base, '/\\') . '/oversee-private';
    }

    public static function ensure_storage_dir() {
        $root = self::storage_root();
        if (!is_dir($root)) {
            @mkdir($root, 0750, true);
        }
        // Deny direct web access. Apache + nginx rule files; the nginx rule
        // is informational since nginx ignores .htaccess but admins are
        // documented to mirror the rule in their server config.
        $htaccess = $root . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n");
        }
        $index = $root . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }
        $webconfig = $root . '/web.config';
        if (!file_exists($webconfig)) {
            @file_put_contents($webconfig, "<?xml version=\"1.0\"?>\n<configuration><system.webServer><authorization><deny users=\"*\"/></authorization></system.webServer></configuration>\n");
        }
    }

    /* ---------------- Validation ---------------- */

    public static function is_allowed_mime($mime) {
        return is_string($mime) && array_key_exists(strtolower($mime), self::ALLOWED_MIME);
    }

    public static function validate_upload($file_name, $mime, $size_bytes) {
        if (!is_string($file_name) || $file_name === '') {
            return new WP_Error('ocd_file_invalid', 'File name required.');
        }
        if ((int) $size_bytes <= 0) {
            return new WP_Error('ocd_file_invalid', 'Empty file.');
        }
        if ((int) $size_bytes > self::MAX_BYTES) {
            return new WP_Error('ocd_file_too_large', sprintf('File exceeds the %d MB limit.', (int) (self::MAX_BYTES / 1048576)));
        }
        if (!self::is_allowed_mime($mime)) {
            return new WP_Error('ocd_file_mime', sprintf('File type %s is not allowed.', $mime));
        }
        return true;
    }

    public static function is_image_mime($mime) {
        return is_string($mime) && strpos($mime, 'image/') === 0;
    }

    /* ---------------- Storage / DB ---------------- */

    /**
     * Stores an uploaded file (raw bytes) and creates the DB record.
     *
     * @param array  $meta    project_id, task_id, milestone_id, owner_user_id,
     *                        uploader_user_id, uploader_role, folder, visibility,
     *                        approval_status, file_name, mime_type
     * @param string $bytes   raw file contents (already read from $_FILES tmp)
     * @return array|WP_Error file record or error
     */
    public static function store($meta, $bytes) {
        global $wpdb;

        $owner = (int) ($meta['owner_user_id'] ?? 0);
        if ($owner <= 0) {
            return new WP_Error('ocd_file_owner', 'owner_user_id required.');
        }
        $file_name = self::sanitize_file_name((string) ($meta['file_name'] ?? ''));
        $mime      = strtolower((string) ($meta['mime_type'] ?? ''));
        $size      = is_string($bytes) ? strlen($bytes) : 0;
        $check = self::validate_upload($file_name, $mime, $size);
        if (is_wp_error($check)) return $check;

        $folder = in_array($meta['folder'] ?? '', self::FOLDERS, true) ? $meta['folder'] : 'intake';
        $visibility = in_array($meta['visibility'] ?? '', self::VISIBILITIES, true) ? $meta['visibility'] : 'client';
        $approval = in_array($meta['approval_status'] ?? '', self::APPROVAL_STATES, true) ? $meta['approval_status'] : 'not_required';
        $role     = in_array($meta['uploader_role'] ?? '', ['customer', 'staff', 'admin'], true) ? $meta['uploader_role'] : 'customer';

        self::ensure_storage_dir();

        $ext = self::ALLOWED_MIME[$mime] ?? 'bin';
        $stored_name = sprintf('%s-%s.%s', date('Ymd-His'), self::random_token(16), $ext);
        $owner_dir = self::storage_root() . '/' . (int) $owner;
        if (!is_dir($owner_dir)) {
            @mkdir($owner_dir, 0750, true);
        }
        $path = $owner_dir . '/' . $stored_name;
        $written = @file_put_contents($path, $bytes);
        if ($written === false) {
            return new WP_Error('ocd_file_write', 'Could not write file to private storage.');
        }
        @chmod($path, 0640);

        // Bump version if a same-named file already exists for this project/task.
        $previous_version = 0;
        $project_id = isset($meta['project_id']) ? (int) $meta['project_id'] : 0;
        if ($project_id) {
            $previous_version = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT MAX(version) FROM ' . OCD_Schema::table('project_files')
                . ' WHERE project_id = %d AND file_name = %s',
                $project_id, $file_name
            ));
        }

        $row = [
            'project_id'       => $project_id ?: null,
            'task_id'          => isset($meta['task_id']) ? (int) $meta['task_id'] : null,
            'milestone_id'     => isset($meta['milestone_id']) ? (int) $meta['milestone_id'] : null,
            'owner_user_id'    => $owner,
            'uploader_user_id' => isset($meta['uploader_user_id']) ? (int) $meta['uploader_user_id'] : $owner,
            'uploader_role'    => $role,
            'folder'           => $folder,
            'file_name'        => $file_name,
            'stored_name'      => $stored_name,
            'mime_type'        => $mime,
            'size_bytes'       => (int) $size,
            'version'          => max(1, $previous_version + 1),
            'visibility'       => $visibility,
            'approval_status'  => $approval,
            'checksum'         => is_string($bytes) ? hash('sha256', $bytes) : null,
            'created_at'       => current_time('mysql', true),
        ];
        $wpdb->insert(OCD_Schema::table('project_files'), $row);
        return self::get((int) $wpdb->insert_id);
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . OCD_Schema::table('project_files') . ' WHERE id = %d', (int) $id),
            ARRAY_A
        ) ?: null;
    }

    public static function path_for($file) {
        if (!is_array($file)) return null;
        $owner = (int) ($file['owner_user_id'] ?? 0);
        $stored = (string) ($file['stored_name'] ?? '');
        if (!$owner || $stored === '') return null;
        $stored = basename($stored); // belt-and-suspenders against ../
        return self::storage_root() . '/' . $owner . '/' . $stored;
    }

    public static function delete($id) {
        global $wpdb;
        $f = self::get((int) $id);
        if (!$f) return false;
        $p = self::path_for($f);
        if ($p && is_file($p)) {
            @unlink($p);
        }
        return $wpdb->delete(OCD_Schema::table('project_files'), ['id' => (int) $id]);
    }

    public static function archive($id) {
        global $wpdb;
        return $wpdb->update(
            OCD_Schema::table('project_files'),
            ['archived' => 1, 'archived_at' => current_time('mysql', true), 'folder' => 'archive'],
            ['id' => (int) $id]
        );
    }

    public static function set_approval($id, $status, $comment, $approver_user_id) {
        global $wpdb;
        if (!in_array($status, self::APPROVAL_STATES, true)) {
            return new WP_Error('ocd_invalid_approval', 'Invalid approval status.');
        }
        $update = [
            'approval_status'  => $status,
            'approval_comment' => $comment !== null ? wp_kses_post((string) $comment) : null,
            'approved_by'      => (int) $approver_user_id,
            'approved_at'      => ($status === 'approved' || $status === 'rejected') ? current_time('mysql', true) : null,
        ];
        $wpdb->update(OCD_Schema::table('project_files'), $update, ['id' => (int) $id]);
        return self::get((int) $id);
    }

    public static function record_view($id) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . OCD_Schema::table('project_files')
            . ' SET view_count = view_count + 1, last_viewed_at = %s WHERE id = %d',
            current_time('mysql', true), (int) $id
        ));
    }

    public static function record_download($id) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . OCD_Schema::table('project_files') . ' SET download_count = download_count + 1 WHERE id = %d',
            (int) $id
        ));
    }

    /* ---------------- Listings ---------------- */

    public static function for_user($user_id, $args = []) {
        global $wpdb;
        $where  = 'owner_user_id = %d AND archived = 0';
        $params = [(int) $user_id];

        // Customer-facing listings only ever return client-visible records.
        $context = $args['context'] ?? 'customer';
        if ($context === 'customer') {
            $where .= ' AND visibility = "client"';
        }
        if (!empty($args['project_id'])) {
            $where .= ' AND project_id = %d';
            $params[] = (int) $args['project_id'];
        }
        if (!empty($args['task_id'])) {
            $where .= ' AND task_id = %d';
            $params[] = (int) $args['task_id'];
        }
        if (!empty($args['folder']) && in_array($args['folder'], self::FOLDERS, true)) {
            $where .= ' AND folder = %s';
            $params[] = $args['folder'];
        }
        if (!empty($args['approval_status']) && in_array($args['approval_status'], self::APPROVAL_STATES, true)) {
            $where .= ' AND approval_status = %s';
            $params[] = $args['approval_status'];
        }
        $params[] = max(1, min(500, (int) ($args['per_page'] ?? 200)));
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . OCD_Schema::table('project_files')
            . ' WHERE ' . $where . ' ORDER BY created_at DESC LIMIT %d',
            $params
        ), ARRAY_A) ?: [];
    }

    public static function all($args = []) {
        global $wpdb;
        $where  = '1=1';
        $params = [];
        if (!empty($args['project_id'])) { $where .= ' AND project_id = %d'; $params[] = (int) $args['project_id']; }
        if (!empty($args['user_id']))    { $where .= ' AND owner_user_id = %d'; $params[] = (int) $args['user_id']; }
        if (!empty($args['folder']) && in_array($args['folder'], self::FOLDERS, true)) {
            $where .= ' AND folder = %s';
            $params[] = $args['folder'];
        }
        if (isset($args['archived'])) {
            $where .= ' AND archived = %d';
            $params[] = $args['archived'] ? 1 : 0;
        }
        if (!empty($args['approval_status']) && in_array($args['approval_status'], self::APPROVAL_STATES, true)) {
            $where .= ' AND approval_status = %s';
            $params[] = $args['approval_status'];
        }
        $params[] = max(1, min(500, (int) ($args['per_page'] ?? 100)));
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . OCD_Schema::table('project_files')
            . ' WHERE ' . $where . ' ORDER BY created_at DESC LIMIT %d',
            $params
        ), ARRAY_A) ?: [];
    }

    /**
     * Public-facing presentation: strip storage paths, expose a permission-
     * checked download URL only.
     */
    public static function present($file, $for_admin = false) {
        if (!is_array($file)) return null;
        $public = [
            'id'              => (int) $file['id'],
            'project_id'      => isset($file['project_id']) ? (int) $file['project_id'] : null,
            'task_id'         => isset($file['task_id']) ? (int) $file['task_id'] : null,
            'milestone_id'    => isset($file['milestone_id']) ? (int) $file['milestone_id'] : null,
            'owner_user_id'   => (int) $file['owner_user_id'],
            'uploader_role'   => $file['uploader_role'],
            'folder'          => $file['folder'],
            'file_name'       => $file['file_name'],
            'mime_type'       => $file['mime_type'],
            'size_bytes'      => (int) $file['size_bytes'],
            'version'         => (int) $file['version'],
            'visibility'      => $file['visibility'],
            'approval_status' => $file['approval_status'],
            'is_image'        => self::is_image_mime($file['mime_type']),
            'download_url'    => self::download_url((int) $file['id']),
            'created_at'      => $file['created_at'],
        ];
        if ($for_admin) {
            $public['approval_comment'] = $file['approval_comment'] ?? null;
            $public['view_count']       = (int) $file['view_count'];
            $public['download_count']   = (int) $file['download_count'];
            $public['last_viewed_at']   = $file['last_viewed_at'] ?? null;
            $public['archived']         = (bool) $file['archived'];
            $public['checksum']         = $file['checksum'] ?? null;
        }
        return $public;
    }

    public static function download_url($id) {
        return rest_url('ocd/v1/files/' . (int) $id . '/download');
    }

    /* ---------------- helpers ---------------- */

    private static function sanitize_file_name($name) {
        $name = function_exists('sanitize_file_name') ? sanitize_file_name($name) : preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
        $name = ltrim($name, '.');
        if (strlen($name) > 200) $name = substr($name, 0, 200);
        return $name ?: 'file';
    }

    private static function random_token($len) {
        if (function_exists('wp_generate_password')) {
            return wp_generate_password($len, false, false);
        }
        try {
            return bin2hex(random_bytes((int) ceil($len / 2)));
        } catch (Exception $e) {
            return substr(md5(uniqid('', true)), 0, $len);
        }
    }
}
