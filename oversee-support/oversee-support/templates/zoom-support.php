<?php
/**
 * Zoom Support Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$portal_title = oversee_get_portal_title();

global $wpdb;
$agents = $wpdb->get_results(
    "SELECT a.*, u.display_name, u.user_email 
     FROM {$wpdb->prefix}oversee_agents a
     JOIN {$wpdb->users} u ON a.user_id = u.ID
     WHERE a.is_online = 1
     ORDER BY u.display_name ASC",
    ARRAY_A
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Support - <?php echo esc_html($portal_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/public.css?v=2.0.0'); ?>">
</head>
<body>
    <div class="page-wrapper">
        <header class="site-header">
            <div class="header-inner">
                <nav class="header-nav">
                    <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-book"></i><span>Knowledge Base</span></a>
                    <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>"><i class="fa-solid fa-ticket"></i><span>My Tickets</span></a>
                    <a href="<?php echo esc_url(oversee_support_url('zoom')); ?>" class="active"><i class="fa-solid fa-video"></i><span>Live Support</span></a>
                </nav>
                <div class="header-actions">
                    <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i><span>Submit Ticket</span></a>
                </div>
            </div>
        </header>

        <nav class="breadcrumb">
            <div class="breadcrumb-inner">
                <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-home"></i> Help Center</a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="current">Live Support</span>
            </div>
        </nav>

        <main class="main-content">
            <section class="zoom-section">
                <div class="zoom-header">
                    <h1>Talk to Our Support Team</h1>
                    <p>Schedule a live video call with one of our support specialists.</p>
                </div>
                
                <?php if (empty($agents)): ?>
                <div class="no-agents">
                    <i class="fa-solid fa-user-clock"></i>
                    <h3>No Agents Available</h3>
                    <p>Our support team is currently offline. Please try again later or submit a ticket.</p>
                    <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;">
                        <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary"><i class="fa-solid fa-ticket"></i> Submit a Ticket</a>
                        <a href="<?php echo esc_url(oversee_kb_url()); ?>" class="btn btn-secondary"><i class="fa-solid fa-book"></i> Browse Help Center</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="agents-grid">
                    <?php foreach ($agents as $agent): ?>
                    <div class="agent-card">
                        <div class="agent-avatar">
                            <?php $avatar = get_avatar_url($agent['user_id'], ['size' => 160]); if ($avatar): ?>
                            <img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($agent['display_name']); ?>">
                            <?php else: ?>
                            <div class="avatar-placeholder"><?php echo esc_html(strtoupper(substr($agent['display_name'], 0, 1))); ?></div>
                            <?php endif; ?>
                        </div>
                        <h3 class="agent-name"><?php echo esc_html($agent['display_name']); ?></h3>
                        <p class="agent-role">Support Specialist</p>
                        <div class="agent-status online"><span class="online-dot"></span> Available Now</div>
                        <?php if (!empty($agent['zoom_link'])): ?>
                        <a href="<?php echo esc_url($agent['zoom_link']); ?>" target="_blank" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-video"></i> Start Call</a>
                        <?php else: ?>
                        <button class="btn btn-secondary" style="width:100%;" disabled><i class="fa-solid fa-clock"></i> No Zoom Link</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-top:60px;text-align:center;max-width:600px;margin-left:auto;margin-right:auto;">
                    <h3 style="font-size:20px;font-weight:600;margin-bottom:16px;">How It Works</h3>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:24px;">
                        <div>
                            <div style="width:48px;height:48px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;"><i class="fa-solid fa-user-check" style="color:var(--primary);font-size:20px;"></i></div>
                            <h4 style="font-size:14px;font-weight:600;margin-bottom:4px;">1. Choose Agent</h4>
                            <p style="font-size:13px;color:var(--text-muted);">Select an available specialist</p>
                        </div>
                        <div>
                            <div style="width:48px;height:48px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;"><i class="fa-solid fa-video" style="color:var(--primary);font-size:20px;"></i></div>
                            <h4 style="font-size:14px;font-weight:600;margin-bottom:4px;">2. Start Call</h4>
                            <p style="font-size:13px;color:var(--text-muted);">Click to join their Zoom</p>
                        </div>
                        <div>
                            <div style="width:48px;height:48px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;"><i class="fa-solid fa-comments" style="color:var(--primary);font-size:20px;"></i></div>
                            <h4 style="font-size:14px;font-weight:600;margin-bottom:4px;">3. Get Help</h4>
                            <p style="font-size:13px;color:var(--text-muted);">Discuss your issue live</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <p>Powered by <a href="https://overseecrm.com" target="_blank">OverseeCRM</a></p>
        </footer>
    </div>
</body>
</html>
