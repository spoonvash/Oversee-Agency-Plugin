<?php
/**
 * Pending actions aggregator. Powers the home dashboard "What needs your
 * attention?" panel — surfaces tasks needing client input, files awaiting
 * approval/upload, billing actions (failed renewals, change-payment), and
 * required onboarding steps.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Pending_Actions {

    public static function for_user($user_id, $subscriptions = [], $orders = []) {
        $items = [];

        // ---- Tasks awaiting client input ----
        $tasks = OCD_Tasks::all([
            'user_id'    => (int) $user_id,
            'visibility' => 'client',
            'task_type'  => 'client_required',
            'per_page'   => 50,
        ]);
        foreach ($tasks as $t) {
            if (!OCD_Tasks::requires_client_action($t)) continue;
            $items[] = [
                'kind'        => 'task',
                'priority'    => self::task_priority($t),
                'title'       => $t['title'],
                'description' => 'A task needs your attention.',
                'task_id'     => (int) $t['id'],
                'project_id'  => isset($t['project_id']) ? (int) $t['project_id'] : null,
                'due_date'    => $t['due_date'],
                'cta_label'   => 'Open task',
            ];
        }

        // ---- Files awaiting approval ----
        $pending_files = OCD_Project_Files::for_user((int) $user_id, [
            'context'         => 'customer',
            'approval_status' => 'pending',
            'per_page'        => 25,
        ]);
        foreach ($pending_files as $f) {
            $items[] = [
                'kind'        => 'file_approval',
                'priority'    => 'high',
                'title'       => 'Approve: ' . $f['file_name'],
                'description' => 'A deliverable is waiting for your approval.',
                'file_id'     => (int) $f['id'],
                'project_id'  => isset($f['project_id']) ? (int) $f['project_id'] : null,
                'cta_label'   => 'Review file',
                'download_url'=> OCD_Project_Files::download_url((int) $f['id']),
            ];
        }

        // ---- Billing actions ----
        if (class_exists('OCD_Billing')) {
            // Failed/pending orders that need payment.
            foreach ((array) $orders as $order) {
                $status = (string) ($order['status'] ?? '');
                if (!in_array($status, ['pending', 'failed', 'on-hold'], true)) continue;
                $items[] = [
                    'kind'        => 'billing_pay_order',
                    'priority'    => $status === 'failed' ? 'urgent' : 'high',
                    'title'       => sprintf('Order #%s needs payment', $order['number'] ?? (string) ($order['id'] ?? '')),
                    'description' => 'Complete payment to keep your service active.',
                    'order_id'    => (int) ($order['id'] ?? 0),
                    'cta_label'   => 'Pay now',
                    'pay_url'     => OCD_Billing::order_pay_url($order),
                ];
            }
            // Subscriptions where the next renewal is imminent and payment can be changed.
            foreach ((array) $subscriptions as $sub) {
                if (($sub['status'] ?? '') !== 'on-hold') continue;
                $eligibility = OCD_Billing::subscription_payment_eligibility($sub);
                if (!$eligibility['can_change_payment']) continue;
                $items[] = [
                    'kind'         => 'billing_change_card',
                    'priority'     => 'high',
                    'title'        => sprintf('Update card on subscription #%d', (int) $sub['id']),
                    'description'  => 'A renewal payment failed. Update the card to reactivate.',
                    'subscription_id' => (int) $sub['id'],
                    'cta_label'    => 'Update payment',
                    'change_payment_url' => $eligibility['change_payment_url'],
                ];
            }
        }

        // ---- Sort: urgent → high → normal → low ----
        $rank = ['urgent' => 0, 'high' => 1, 'normal' => 2, 'low' => 3];
        usort($items, function ($a, $b) use ($rank) {
            $ra = $rank[$a['priority'] ?? 'normal'] ?? 2;
            $rb = $rank[$b['priority'] ?? 'normal'] ?? 2;
            if ($ra !== $rb) return $ra - $rb;
            $da = $a['due_date'] ?? '9999-12-31';
            $db = $b['due_date'] ?? '9999-12-31';
            return strcmp($da, $db);
        });

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    private static function task_priority($task) {
        $p = $task['priority'] ?? 'normal';
        // A `needs_your_input` task is at minimum "high" — clients should see it.
        if (($task['status'] ?? '') === 'needs_your_input' && $p === 'normal') return 'high';
        return $p;
    }
}
