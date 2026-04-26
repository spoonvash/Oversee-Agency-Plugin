<?php
/**
 * Project timelines + milestones. Each project belongs to a customer and may
 * be linked to a WooCommerce order/subscription/product.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Projects {

    const VALID_STATUSES           = ['planning', 'in-progress', 'on-hold', 'review', 'completed', 'cancelled'];
    const VALID_MILESTONE_STATUSES = ['pending', 'in-progress', 'completed', 'blocked'];

    public static function create($data) {
        global $wpdb;
        $required = ['user_id', 'title'];
        foreach ($required as $f) {
            if (empty($data[$f])) {
                return new WP_Error('ocd_invalid_project', sprintf('%s is required.', $f));
            }
        }
        $status = isset($data['status']) ? $data['status'] : 'planning';
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return new WP_Error('ocd_invalid_status', 'Invalid project status.');
        }

        $row = [
            'user_id'            => (int) $data['user_id'],
            'wc_order_id'        => isset($data['wc_order_id']) ? (int) $data['wc_order_id'] : null,
            'wc_subscription_id' => isset($data['wc_subscription_id']) ? (int) $data['wc_subscription_id'] : null,
            'wc_product_id'      => isset($data['wc_product_id']) ? (int) $data['wc_product_id'] : null,
            'title'              => sanitize_text_field($data['title']),
            'description'        => isset($data['description']) ? wp_kses_post($data['description']) : null,
            'status'             => $status,
            'progress'           => isset($data['progress']) ? max(0, min(100, (int) $data['progress'])) : 0,
            'start_date'         => self::sanitize_date($data['start_date'] ?? null),
            'target_date'        => self::sanitize_date($data['target_date'] ?? null),
            'created_at'         => current_time('mysql', true),
            'updated_at'         => current_time('mysql', true),
        ];
        $wpdb->insert(OCD_Schema::table('projects'), $row);
        return self::get((int) $wpdb->insert_id);
    }

    public static function update($id, $data) {
        global $wpdb;
        $project = self::get((int) $id);
        if (!$project) return new WP_Error('ocd_no_project', 'Project not found.');

        $update = [];
        if (isset($data['title']))       $update['title']       = sanitize_text_field($data['title']);
        if (isset($data['description'])) $update['description'] = wp_kses_post($data['description']);
        if (isset($data['status'])) {
            if (!in_array($data['status'], self::VALID_STATUSES, true)) {
                return new WP_Error('ocd_invalid_status', 'Invalid project status.');
            }
            $update['status'] = $data['status'];
            if ($data['status'] === 'completed') {
                $update['completed_at'] = current_time('mysql', true);
                $update['progress']     = 100;
            }
        }
        if (isset($data['progress'])) $update['progress'] = max(0, min(100, (int) $data['progress']));
        if (array_key_exists('start_date', $data))  $update['start_date']  = self::sanitize_date($data['start_date']);
        if (array_key_exists('target_date', $data)) $update['target_date'] = self::sanitize_date($data['target_date']);

        if (!empty($update)) {
            $update['updated_at'] = current_time('mysql', true);
            $wpdb->update(OCD_Schema::table('projects'), $update, ['id' => (int) $id]);
        }
        return self::get((int) $id);
    }

    public static function delete($id) {
        global $wpdb;
        $wpdb->delete(OCD_Schema::table('milestones'), ['project_id' => (int) $id]);
        return $wpdb->delete(OCD_Schema::table('projects'), ['id' => (int) $id]);
    }

    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . OCD_Schema::table('projects') . ' WHERE id = %d', (int) $id),
            ARRAY_A
        );
        if (!$row) return null;
        $row['milestones'] = self::milestones_for_project((int) $row['id']);
        return $row;
    }

    public static function for_user($user_id) {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . OCD_Schema::table('projects') . ' WHERE user_id = %d ORDER BY created_at DESC',
                (int) $user_id
            ),
            ARRAY_A
        ) ?: [];
        foreach ($rows as &$r) {
            $r['milestones'] = self::milestones_for_project((int) $r['id']);
        }
        return $rows;
    }

    public static function all($args = []) {
        global $wpdb;
        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 50)));
        $where = '1=1';
        $params = [];
        if (!empty($args['user_id'])) {
            $where .= ' AND user_id = %d';
            $params[] = (int) $args['user_id'];
        }
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }
        $sql = 'SELECT * FROM ' . OCD_Schema::table('projects') . ' WHERE ' . $where . ' ORDER BY updated_at DESC LIMIT %d';
        $params[] = $per_page;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
        foreach ($rows as &$r) {
            $r['milestones'] = self::milestones_for_project((int) $r['id']);
        }
        return $rows;
    }

    /* ---------------- Milestones ---------------- */

    public static function add_milestone($project_id, $data) {
        global $wpdb;
        if (!self::get((int) $project_id)) {
            return new WP_Error('ocd_no_project', 'Project not found.');
        }
        if (empty($data['title'])) return new WP_Error('ocd_invalid_milestone', 'Title required.');
        $status = $data['status'] ?? 'pending';
        if (!in_array($status, self::VALID_MILESTONE_STATUSES, true)) {
            return new WP_Error('ocd_invalid_status', 'Invalid milestone status.');
        }
        $row = [
            'project_id' => (int) $project_id,
            'title'      => sanitize_text_field($data['title']),
            'note'       => isset($data['note']) ? wp_kses_post($data['note']) : null,
            'status'     => $status,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'due_date'   => self::sanitize_date($data['due_date'] ?? null),
            'created_at' => current_time('mysql', true),
        ];
        $wpdb->insert(OCD_Schema::table('milestones'), $row);
        $id = (int) $wpdb->insert_id;
        self::recalc_progress((int) $project_id);
        return self::get_milestone($id);
    }

    public static function update_milestone($id, $data) {
        global $wpdb;
        $m = self::get_milestone((int) $id);
        if (!$m) return new WP_Error('ocd_no_milestone', 'Milestone not found.');

        $update = [];
        if (isset($data['title'])) $update['title'] = sanitize_text_field($data['title']);
        if (isset($data['note']))  $update['note']  = wp_kses_post($data['note']);
        if (isset($data['status'])) {
            if (!in_array($data['status'], self::VALID_MILESTONE_STATUSES, true)) {
                return new WP_Error('ocd_invalid_status', 'Invalid milestone status.');
            }
            $update['status'] = $data['status'];
            if ($data['status'] === 'completed') {
                $update['completed_at'] = current_time('mysql', true);
            }
        }
        if (isset($data['sort_order'])) $update['sort_order'] = (int) $data['sort_order'];
        if (array_key_exists('due_date', $data)) $update['due_date'] = self::sanitize_date($data['due_date']);

        if (!empty($update)) {
            $wpdb->update(OCD_Schema::table('milestones'), $update, ['id' => (int) $id]);
        }
        self::recalc_progress((int) $m['project_id']);
        return self::get_milestone((int) $id);
    }

    public static function delete_milestone($id) {
        global $wpdb;
        $m = self::get_milestone((int) $id);
        if (!$m) return false;
        $res = $wpdb->delete(OCD_Schema::table('milestones'), ['id' => (int) $id]);
        self::recalc_progress((int) $m['project_id']);
        return $res;
    }

    public static function get_milestone($id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . OCD_Schema::table('milestones') . ' WHERE id = %d', (int) $id),
            ARRAY_A
        ) ?: null;
    }

    public static function milestones_for_project($project_id) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . OCD_Schema::table('milestones')
                . ' WHERE project_id = %d ORDER BY sort_order ASC, id ASC',
                (int) $project_id
            ),
            ARRAY_A
        ) ?: [];
    }

    private static function recalc_progress($project_id) {
        global $wpdb;
        $rows = self::milestones_for_project((int) $project_id);
        if (empty($rows)) return;
        $done = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'completed') $done++;
        }
        $progress = (int) round(($done / count($rows)) * 100);
        $update = ['progress' => $progress, 'updated_at' => current_time('mysql', true)];
        if ($progress === 100) {
            $update['status']       = 'completed';
            $update['completed_at'] = current_time('mysql', true);
        }
        $wpdb->update(OCD_Schema::table('projects'), $update, ['id' => (int) $project_id]);
    }

    private static function sanitize_date($d) {
        if (!$d) return null;
        $ts = strtotime((string) $d);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
