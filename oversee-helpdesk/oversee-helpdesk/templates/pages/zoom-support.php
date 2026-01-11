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
