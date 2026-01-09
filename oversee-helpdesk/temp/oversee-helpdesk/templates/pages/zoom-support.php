<?php
/**
 * Live Support Page - With Agent Calendar Booking
 * Shows agents who are online and have enabled live support with calendar embed
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get agents: online + show_on_live_support enabled + has calendar embed
global $wpdb;
$agents = $wpdb->get_results(
    "SELECT a.id, a.calendar_embed, u.display_name, u.user_email 
     FROM {$wpdb->prefix}oversee_agents a
     JOIN {$wpdb->users} u ON a.wp_user_id = u.ID
     WHERE a.is_online = 1 
       AND a.show_on_live_support = 1
       AND a.calendar_embed IS NOT NULL 
       AND a.calendar_embed != ''
     ORDER BY u.display_name ASC",
    ARRAY_A
);
?>

<style>
    .zoom-section { max-width: 900px; margin: 0 auto; padding: 40px 24px; }
    .zoom-header { text-align: center; margin-bottom: 40px; }
    .zoom-header h1 { font-size: 32px; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
    .zoom-header p { font-size: 16px; color: #64748b; }
    
    .agents-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 32px; margin-bottom: 40px; }
    .agents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 24px; }
    
    .agent-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; text-align: center; transition: all 0.2s; }
    .agent-card:hover { border-color: var(--primary, #f97316); box-shadow: 0 4px 12px rgba(249, 115, 22, 0.15); }
    
    .agent-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary, #f97316), var(--primary-hover, #ea580c)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 600; margin: 0 auto 16px; }
    .agent-name { font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
    .agent-role { font-size: 14px; color: #64748b; margin-bottom: 12px; }
    .agent-status { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #10b981; margin-bottom: 16px; }
    .agent-status .dot { width: 8px; height: 8px; background: #10b981; border-radius: 50%; animation: pulse 2s infinite; }
    
    .btn-book { width: 100%; padding: 12px 20px; background: var(--primary, #f97316); color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 500; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s; }
    .btn-book:hover { background: var(--primary-hover, #ea580c); }
    
    /* No agents state */
    .no-agents { text-align: center; padding: 60px 20px; }
    .no-agents-icon { width: 80px; height: 80px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    .no-agents-icon i { font-size: 32px; color: #94a3b8; }
    .no-agents h3 { font-size: 20px; font-weight: 600; color: #1e293b; margin-bottom: 8px; }
    .no-agents p { color: #64748b; margin-bottom: 20px; }
    
    /* How it works */
    .how-it-works { text-align: center; padding: 40px 0; }
    .how-it-works h2 { font-size: 24px; font-weight: 600; color: #1e293b; margin-bottom: 32px; }
    .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 32px; }
    .step { text-align: center; }
    .step-icon { width: 60px; height: 60px; background: var(--primary-light, #fff7ed); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
    .step-icon i { font-size: 24px; color: var(--primary, #f97316); }
    .step h3 { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 8px; }
    .step p { font-size: 14px; color: #64748b; }
    
    /* Lightbox Modal */
    .calendar-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 10000; align-items: center; justify-content: center; padding: 20px; }
    .calendar-modal.show { display: flex; }
    .calendar-modal-content { background: #fff; border-radius: 16px; width: 100%; max-width: 700px; max-height: 90vh; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: modalIn 0.2s ease-out; }
    .calendar-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #e2e8f0; }
    .calendar-modal-header h3 { font-size: 18px; font-weight: 600; color: #1e293b; margin: 0; }
    .calendar-modal-close { width: 36px; height: 36px; border: none; background: #f1f5f9; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #64748b; transition: all 0.15s; }
    .calendar-modal-close:hover { background: #e2e8f0; color: #1e293b; }
    .calendar-modal-body { padding: 0; min-height: 500px; }
    .calendar-modal-body iframe { width: 100%; height: 500px; border: none; }
    
    @keyframes modalIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    
    @media (max-width: 640px) {
        .zoom-header h1 { font-size: 24px; }
        .agents-grid { grid-template-columns: 1fr; }
        .calendar-modal-content { max-height: 95vh; }
        .calendar-modal-body iframe { height: 400px; }
    }
</style>

<nav class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-home"></i> Help Center</a>
        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="current">Live Support</span>
    </div>
</nav>

<section class="zoom-section">
    <div class="zoom-header">
        <h1>Talk to Our Support Team</h1>
        <p>Schedule a live video call with one of our support specialists.</p>
    </div>
    
    <div class="agents-container">
        <?php if (empty($agents)): ?>
        <div class="no-agents">
            <div class="no-agents-icon"><i class="fa-solid fa-headset"></i></div>
            <h3>No Agents Available</h3>
            <p>Our support team is currently offline. Please check back later or submit a ticket.</p>
            <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Submit a Ticket
            </a>
        </div>
        <?php else: ?>
        <div class="agents-grid">
            <?php foreach ($agents as $agent): 
                $name = $agent['display_name'] ?: 'Support Agent';
                $initials = '';
                $parts = explode(' ', $name);
                foreach ($parts as $part) {
                    $initials .= strtoupper(substr($part, 0, 1));
                }
                $initials = substr($initials, 0, 2);
            ?>
            <div class="agent-card">
                <div class="agent-avatar"><?php echo esc_html($initials); ?></div>
                <h3 class="agent-name"><?php echo esc_html($name); ?></h3>
                <p class="agent-role">Support Specialist</p>
                <div class="agent-status">
                    <span class="dot"></span> Available Now
                </div>
                <button type="button" class="btn-book" onclick="openCalendar(<?php echo (int)$agent['id']; ?>, '<?php echo esc_js($name); ?>')">
                    <i class="fa-solid fa-calendar-check"></i> Book Time
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="how-it-works">
        <h2>How It Works</h2>
        <div class="steps">
            <div class="step">
                <div class="step-icon"><i class="fa-solid fa-user-check"></i></div>
                <h3>1. Choose Agent</h3>
                <p>Select an available specialist</p>
            </div>
            <div class="step">
                <div class="step-icon"><i class="fa-solid fa-calendar"></i></div>
                <h3>2. Book Time</h3>
                <p>Pick a convenient time slot</p>
            </div>
            <div class="step">
                <div class="step-icon"><i class="fa-solid fa-comments"></i></div>
                <h3>3. Get Help</h3>
                <p>Discuss your issue live</p>
            </div>
        </div>
    </div>
</section>

<!-- Calendar Lightbox Modal -->
<div class="calendar-modal" id="calendarModal">
    <div class="calendar-modal-content">
        <div class="calendar-modal-header">
            <h3 id="calendarModalTitle">Schedule a Call</h3>
            <button type="button" class="calendar-modal-close" onclick="closeCalendar()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="calendar-modal-body" id="calendarModalBody">
            <!-- Calendar embed will be inserted here -->
        </div>
    </div>
</div>

<script>
    // Store calendar embeds by agent ID
    const calendarEmbeds = {
        <?php foreach ($agents as $agent): ?>
        <?php echo (int)$agent['id']; ?>: <?php echo json_encode($agent['calendar_embed']); ?>,
        <?php endforeach; ?>
    };
    
    function openCalendar(agentId, agentName) {
        const embed = calendarEmbeds[agentId];
        if (!embed) {
            alert('Calendar not available');
            return;
        }
        
        document.getElementById('calendarModalTitle').textContent = 'Schedule with ' + agentName;
        document.getElementById('calendarModalBody').innerHTML = embed;
        document.getElementById('calendarModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeCalendar() {
        document.getElementById('calendarModal').classList.remove('show');
        document.getElementById('calendarModalBody').innerHTML = '';
        document.body.style.overflow = '';
    }
    
    // Close on backdrop click
    document.getElementById('calendarModal').addEventListener('click', function(e) {
        if (e.target === this) closeCalendar();
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeCalendar();
    });
</script>
