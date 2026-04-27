<?php
/**
 * Client tasks/steps assigned by Oversee staff.
 *
 * Monday-style status set:
 *   not_started, in_progress, needs_your_input, waiting_on_oversee,
 *   completed, blocked, cancelled
 *
 * Visibility:
 *   client   — surfaced to the customer dashboard
 *   internal — admin-only, never returned by customer endpoints
 *
 * Task type:
 *   client_required — needs explicit client action (shows in Pending Actions)
 *   internal        — agency work
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Tasks {

    const VALID_STATUSES = [
        'not_started', 'in_progress', 'needs_your_input',
        'waiting_on_oversee', 'completed', 'blocked', 'cancelled',
    ];

    // Plain-language client labels for status keys.
    const STATUS_LABELS = [
        'not_started'        => 'Not Started',
        'in_progress'        => 'In Progress',
        'needs_your_input'   => 'Needs Your Input',
        'waiting_on_oversee' => 'Waiting on Oversee',
        'completed'          => 'Done',
        'blocked'            => 'Blocked',
        'cancelled'          => 'Cancelled',
    ];

    // Statuses a customer is allowed to set on their own task.
    const CUSTOMER_ALLOWED_STATUSES = ['not_started', 'in_progress', 'completed', 'waiting_on_oversee'];

    const TASK_TYPES   = ['client_required', 'internal'];
    const VISIBILITIES = ['client', 'internal'];
    const PRIORITIES   = ['low', 'normal', 'high', 'urgent'];

    public static function status_groups() {
        return [
            ['key' => 'needs_your_input',   'label' => 'Needs Your Input'],
            ['key' => 'in_progress',        'label' => 'In Progress'],
            ['key' => 'waiting_on_oversee', 'label' => 'Waiting on Oversee'],
            ['key' => 'not_started',        'label' => 'Not Started'],
            ['key' => 'blocked',            'label' => 'Blocked'],
            ['key' => 'completed',          'label' => 'Done'],
        ];
    }

    public static function create($data) {
        global $wpdb;
        if (empty($data['user_id']) || empty($data['title'])) {
            return new WP_Error('ocd_invalid_task', 'user_id and title required.');
        }
        $status = $data['status'] ?? 'not_started';
        // Accept legacy "open" alias from older callers.
        if ($status === 'open') $status = 'not_started';
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return new WP_Error('ocd_invalid_status', 'Invalid task status.');
        }

        $task_type  = in_array($data['task_type'] ?? '', self::TASK_TYPES, true)   ? $data['task_type']  : 'internal';
        $visibility = in_array($data['visibility'] ?? '', self::VISIBILITIES, true) ? $data['visibility'] : ($task_type === 'client_required' ? 'client' : 'internal');
        $priority   = in_array($data['priority'] ?? '', self::PRIORITIES, true)     ? $data['priority']   : 'normal';

        $video = self::sanitize_video_field($data['instruction_video_url'] ?? null);
        if (is_wp_error($video)) return $video;
        $image = self::sanitize_image_field($data['instruction_image_url'] ?? null);
        if (is_wp_error($image)) return $image;

        $row = [
            'user_id'                    => (int) $data['user_id'],
            'project_id'                 => isset($data['project_id']) ? (int) $data['project_id'] : null,
            'milestone_id'               => isset($data['milestone_id']) ? (int) $data['milestone_id'] : null,
            'title'                      => sanitize_text_field($data['title']),
            'details'                    => isset($data['details']) ? wp_kses_post($data['details']) : null,
            'status'                     => $status,
            'task_type'                  => $task_type,
            'visibility'                 => $visibility,
            'priority'                   => $priority,
            'internal_notes'             => isset($data['internal_notes']) ? wp_kses_post($data['internal_notes']) : null,
            'assignee_user_id'           => isset($data['assignee_user_id']) ? (int) $data['assignee_user_id'] : null,
            'instruction_video_url'      => $video['url'] ?? null,
            'instruction_video_provider' => $video['provider'] ?? null,
            'instruction_image_url'      => $image['url'] ?? null,
            'due_date'                   => self::sanitize_date($data['due_date'] ?? null),
            'assigned_by'                => isset($data['assigned_by']) ? (int) $data['assigned_by'] : null,
            'created_at'                 => current_time('mysql', true),
            'updated_at'                 => current_time('mysql', true),
        ];
        $wpdb->insert(OCD_Schema::table('tasks'), $row);
        return self::get((int) $wpdb->insert_id);
    }

    public static function update($id, $data, $context = 'admin') {
        global $wpdb;
        $task = self::get((int) $id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.');

        $update = [];
        if ($context === 'customer') {
            // Customers can only change status (within the allow-list) on their own client-visible tasks.
            if ($task['visibility'] !== 'client') {
                return new WP_Error('ocd_forbidden', 'This task is not client-visible.');
            }
            if (isset($data['status'])) {
                if (!in_array($data['status'], self::CUSTOMER_ALLOWED_STATUSES, true)) {
                    return new WP_Error('ocd_invalid_status', 'Customers cannot set this status.');
                }
                $update['status'] = $data['status'];
                $update['completed_at'] = $data['status'] === 'completed' ? current_time('mysql', true) : null;
            }
        } else {
            if (isset($data['title']))   $update['title']   = sanitize_text_field($data['title']);
            if (isset($data['details'])) $update['details'] = wp_kses_post($data['details']);
            if (isset($data['internal_notes'])) $update['internal_notes'] = wp_kses_post($data['internal_notes']);
            if (isset($data['status'])) {
                $s = $data['status'] === 'open' ? 'not_started' : $data['status'];
                if (!in_array($s, self::VALID_STATUSES, true)) {
                    return new WP_Error('ocd_invalid_status', 'Invalid task status.');
                }
                $update['status'] = $s;
                if ($s === 'completed') $update['completed_at'] = current_time('mysql', true);
            }
            if (isset($data['task_type'])) {
                if (!in_array($data['task_type'], self::TASK_TYPES, true)) {
                    return new WP_Error('ocd_invalid_task_type', 'Invalid task type.');
                }
                $update['task_type'] = $data['task_type'];
            }
            if (isset($data['visibility'])) {
                if (!in_array($data['visibility'], self::VISIBILITIES, true)) {
                    return new WP_Error('ocd_invalid_visibility', 'Invalid visibility.');
                }
                $update['visibility'] = $data['visibility'];
            }
            if (isset($data['priority'])) {
                if (!in_array($data['priority'], self::PRIORITIES, true)) {
                    return new WP_Error('ocd_invalid_priority', 'Invalid priority.');
                }
                $update['priority'] = $data['priority'];
            }
            if (isset($data['assignee_user_id'])) $update['assignee_user_id'] = (int) $data['assignee_user_id'];
            if (array_key_exists('due_date', $data)) $update['due_date'] = self::sanitize_date($data['due_date']);
            if (isset($data['project_id'])) $update['project_id'] = (int) $data['project_id'];
            if (isset($data['milestone_id'])) $update['milestone_id'] = (int) $data['milestone_id'];
            if (array_key_exists('instruction_video_url', $data)) {
                $v = self::sanitize_video_field($data['instruction_video_url']);
                if (is_wp_error($v)) return $v;
                $update['instruction_video_url'] = $v['url'] ?? null;
                $update['instruction_video_provider'] = $v['provider'] ?? null;
            }
            if (array_key_exists('instruction_image_url', $data)) {
                $im = self::sanitize_image_field($data['instruction_image_url']);
                if (is_wp_error($im)) return $im;
                $update['instruction_image_url'] = $im['url'] ?? null;
            }
        }
        if (!empty($update)) {
            $update['updated_at'] = current_time('mysql', true);
            $wpdb->update(OCD_Schema::table('tasks'), $update, ['id' => (int) $id]);
        }
        return self::get((int) $id);
    }

    public static function delete($id) {
        global $wpdb;
        $wpdb->delete(OCD_Schema::table('task_comments'), ['task_id' => (int) $id]);
        return $wpdb->delete(OCD_Schema::table('tasks'), ['id' => (int) $id]);
    }

    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . OCD_Schema::table('tasks') . ' WHERE id = %d', (int) $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function for_user($user_id, $args = []) {
        global $wpdb;
        $where  = 'user_id = %d AND visibility = %s';
        $params = [(int) $user_id, 'client'];
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if (!empty($args['project_id'])) {
            $where .= ' AND project_id = %d';
            $params[] = (int) $args['project_id'];
        }
        $params[] = max(1, min(200, (int) ($args['per_page'] ?? 50)));
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . OCD_Schema::table('tasks')
            . ' WHERE ' . $where
            . ' ORDER BY (CASE WHEN status = "completed" THEN 1 ELSE 0 END) ASC, '
            . 'COALESCE(due_date, "9999-12-31") ASC, id DESC LIMIT %d',
            $params
        ), ARRAY_A) ?: [];
    }

    public static function all($args = []) {
        global $wpdb;
        $where = '1=1';
        $params = [];
        if (!empty($args['status']))     { $where .= ' AND status = %s'; $params[] = $args['status']; }
        if (!empty($args['user_id']))    { $where .= ' AND user_id = %d'; $params[] = (int) $args['user_id']; }
        if (!empty($args['project_id'])) { $where .= ' AND project_id = %d'; $params[] = (int) $args['project_id']; }
        if (!empty($args['visibility'])) { $where .= ' AND visibility = %s'; $params[] = $args['visibility']; }
        if (!empty($args['task_type']))  { $where .= ' AND task_type = %s'; $params[] = $args['task_type']; }
        if (!empty($args['assignee_user_id'])) { $where .= ' AND assignee_user_id = %d'; $params[] = (int) $args['assignee_user_id']; }
        $params[] = max(1, min(500, (int) ($args['per_page'] ?? 100)));
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . OCD_Schema::table('tasks') . ' WHERE ' . $where
            . ' ORDER BY updated_at DESC LIMIT %d',
            $params
        ), ARRAY_A) ?: [];
    }

    /**
     * Customer-facing task payload: omits internal_notes, returns status group.
     */
    public static function present_for_customer($task) {
        if (!is_array($task)) return null;
        return [
            'id'                    => (int) $task['id'],
            'project_id'            => isset($task['project_id']) ? (int) $task['project_id'] : null,
            'milestone_id'          => isset($task['milestone_id']) ? (int) $task['milestone_id'] : null,
            'title'                 => $task['title'],
            'details'               => $task['details'],
            'status'                => $task['status'],
            'status_label'          => self::STATUS_LABELS[$task['status']] ?? $task['status'],
            'task_type'             => $task['task_type'],
            'due_date'              => $task['due_date'],
            'completed_at'          => $task['completed_at'],
            'instruction_video_url' => $task['instruction_video_url'],
            'instruction_video_provider' => $task['instruction_video_provider'],
            'instruction_image_url' => $task['instruction_image_url'],
            'requires_client_action' => self::requires_client_action($task),
        ];
    }

    public static function requires_client_action($task) {
        if (!is_array($task)) return false;
        if (($task['visibility'] ?? '') !== 'client') return false;
        if (($task['task_type'] ?? '') !== 'client_required') return false;
        return in_array($task['status'] ?? '', ['needs_your_input', 'not_started', 'in_progress'], true);
    }

    /* ---------------- Comments ---------------- */

    public static function add_comment($task_id, $author_user_id, $author_role, $body, $visibility = 'shared') {
        global $wpdb;
        $body = trim((string) $body);
        if ($body === '') return new WP_Error('ocd_invalid_comment', 'Comment body required.');
        if (!in_array($visibility, ['shared', 'internal'], true)) {
            return new WP_Error('ocd_invalid_visibility', 'Invalid comment visibility.');
        }
        if (!in_array($author_role, ['customer', 'staff', 'admin'], true)) {
            return new WP_Error('ocd_invalid_role', 'Invalid author role.');
        }
        // Customers can never post internal comments.
        if ($author_role === 'customer' && $visibility !== 'shared') {
            return new WP_Error('ocd_forbidden_visibility', 'Customers cannot post internal comments.');
        }
        $task = self::get((int) $task_id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.');

        $row = [
            'task_id'        => (int) $task_id,
            'author_user_id' => (int) $author_user_id,
            'author_role'    => $author_role,
            'visibility'     => $visibility,
            'body'           => wp_kses_post($body),
            'created_at'     => current_time('mysql', true),
        ];
        $wpdb->insert(OCD_Schema::table('task_comments'), $row);
        return self::get_comment((int) $wpdb->insert_id);
    }

    public static function get_comment($id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . OCD_Schema::table('task_comments') . ' WHERE id = %d', (int) $id),
            ARRAY_A
        ) ?: null;
    }

    public static function comments_for_task($task_id, $context = 'customer') {
        global $wpdb;
        $where = 'task_id = %d';
        $params = [(int) $task_id];
        if ($context === 'customer') {
            $where .= ' AND visibility = %s';
            $params[] = 'shared';
        }
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . OCD_Schema::table('task_comments')
            . ' WHERE ' . $where . ' ORDER BY created_at ASC',
            $params
        ), ARRAY_A) ?: [];
    }

    public static function delete_comment($id) {
        global $wpdb;
        return $wpdb->delete(OCD_Schema::table('task_comments'), ['id' => (int) $id]);
    }

    /* ---------------- helpers ---------------- */

    private static function sanitize_date($d) {
        if (!$d) return null;
        $ts = strtotime((string) $d);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private static function sanitize_video_field($url) {
        if ($url === null || $url === '') return ['url' => null, 'provider' => null];
        $res = OCD_Instruction_Media::validate_video_url($url);
        if (is_wp_error($res)) return $res;
        return $res;
    }

    private static function sanitize_image_field($url) {
        if ($url === null || $url === '') return ['url' => null];
        $res = OCD_Instruction_Media::validate_image_url($url);
        if (is_wp_error($res)) return $res;
        return $res;
    }
}
