<?php
/**
 * Client tasks/steps assigned by Oversee staff.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Tasks {

    const VALID_STATUSES = ['open', 'in-progress', 'completed', 'blocked', 'cancelled'];

    public static function create($data) {
        global $wpdb;
        if (empty($data['user_id']) || empty($data['title'])) {
            return new WP_Error('ocd_invalid_task', 'user_id and title required.');
        }
        $status = $data['status'] ?? 'open';
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return new WP_Error('ocd_invalid_status', 'Invalid task status.');
        }
        $row = [
            'user_id'     => (int) $data['user_id'],
            'project_id'  => isset($data['project_id']) ? (int) $data['project_id'] : null,
            'title'       => sanitize_text_field($data['title']),
            'details'     => isset($data['details']) ? wp_kses_post($data['details']) : null,
            'status'      => $status,
            'due_date'    => self::sanitize_date($data['due_date'] ?? null),
            'assigned_by' => isset($data['assigned_by']) ? (int) $data['assigned_by'] : null,
            'created_at'  => current_time('mysql', true),
            'updated_at'  => current_time('mysql', true),
        ];
        $wpdb->insert(OCD_Schema::table('tasks'), $row);
        return self::get((int) $wpdb->insert_id);
    }

    public static function update($id, $data, $context = 'admin') {
        global $wpdb;
        $task = self::get((int) $id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.');

        $update = [];
        // Customer can only change status (complete/reopen) on their own tasks.
        if ($context === 'customer') {
            if (isset($data['status'])) {
                $allowed = ['open', 'in-progress', 'completed'];
                if (!in_array($data['status'], $allowed, true)) {
                    return new WP_Error('ocd_invalid_status', 'Customers cannot set this status.');
                }
                $update['status'] = $data['status'];
                if ($data['status'] === 'completed') {
                    $update['completed_at'] = current_time('mysql', true);
                } else {
                    $update['completed_at'] = null;
                }
            }
        } else {
            if (isset($data['title']))   $update['title']   = sanitize_text_field($data['title']);
            if (isset($data['details'])) $update['details'] = wp_kses_post($data['details']);
            if (isset($data['status'])) {
                if (!in_array($data['status'], self::VALID_STATUSES, true)) {
                    return new WP_Error('ocd_invalid_status', 'Invalid task status.');
                }
                $update['status'] = $data['status'];
                if ($data['status'] === 'completed') {
                    $update['completed_at'] = current_time('mysql', true);
                }
            }
            if (array_key_exists('due_date', $data)) $update['due_date'] = self::sanitize_date($data['due_date']);
            if (isset($data['project_id'])) $update['project_id'] = (int) $data['project_id'];
        }
        if (!empty($update)) {
            $update['updated_at'] = current_time('mysql', true);
            $wpdb->update(OCD_Schema::table('tasks'), $update, ['id' => (int) $id]);
        }
        return self::get((int) $id);
    }

    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete(OCD_Schema::table('tasks'), ['id' => (int) $id]);
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . OCD_Schema::table('tasks') . ' WHERE id = %d', (int) $id),
            ARRAY_A
        ) ?: null;
    }

    public static function for_user($user_id, $args = []) {
        global $wpdb;
        $where  = 'user_id = %d';
        $params = [(int) $user_id];
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
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
        if (!empty($args['status'])) { $where .= ' AND status = %s'; $params[] = $args['status']; }
        if (!empty($args['user_id'])) { $where .= ' AND user_id = %d'; $params[] = (int) $args['user_id']; }
        $params[] = max(1, min(200, (int) ($args['per_page'] ?? 100)));
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . OCD_Schema::table('tasks') . ' WHERE ' . $where
            . ' ORDER BY updated_at DESC LIMIT %d',
            $params
        ), ARRAY_A) ?: [];
    }

    private static function sanitize_date($d) {
        if (!$d) return null;
        $ts = strtotime((string) $d);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
