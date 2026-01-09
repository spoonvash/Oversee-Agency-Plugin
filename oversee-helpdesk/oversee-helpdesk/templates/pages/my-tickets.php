<?php
/**
 * My Tickets Page - Content Only
 * All CSS is in /assets/css/public.css
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<nav class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-home"></i> Help Center</a>
        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="current">My Tickets</span>
    </div>
</nav>

<section class="tickets-section">
    <div class="lookup-card" id="lookupCard">
        <h1><i class="fa-solid fa-ticket"></i> My Support Tickets</h1>
        <p>Enter your email address to view and track your support tickets.</p>
        
        <div id="rememberedEmailBanner" class="remembered-email hidden">
            <i class="fa-solid fa-check-circle"></i>
            <span>Welcome back! Using: <strong id="rememberedEmailText"></strong></span>
            <button type="button" class="btn-link" id="forgetEmailBtn">Use different email</button>
        </div>
        
        <form class="lookup-form" id="lookupForm">
            <div class="form-group">
                <label for="email"><i class="fa-solid fa-envelope"></i> Email Address</label>
                <input type="email" id="email" class="form-control" required placeholder="you@example.com" autocomplete="email">
            </div>
            <button type="submit" class="btn btn-primary" id="lookupBtn">
                <i class="fa-solid fa-search"></i> Find My Tickets
            </button>
        </form>
    </div>
    
    <div class="tickets-card hidden" id="ticketsCard">
        <div class="tickets-header">
            <div>
                <h1>Your Support Tickets</h1>
                <div class="user-badge"><i class="fa-solid fa-user"></i> <span id="ticketsEmail"></span></div>
            </div>
            <button class="btn btn-secondary btn-sm" id="switchAccountBtn">
                <i class="fa-solid fa-right-from-bracket"></i> Switch Account
            </button>
        </div>
        <div id="ticketsContent" class="tickets-list"></div>
    </div>
</section>

<script>
(function() {
    const STORAGE_KEY = 'oversee_customer_email';
    let currentEmail = '';
    
    const lookupCard = document.getElementById('lookupCard');
    const ticketsCard = document.getElementById('ticketsCard');
    const lookupForm = document.getElementById('lookupForm');
    const emailInput = document.getElementById('email');
    const lookupBtn = document.getElementById('lookupBtn');
    const ticketsContent = document.getElementById('ticketsContent');
    const ticketsEmailEl = document.getElementById('ticketsEmail');
    const rememberedBanner = document.getElementById('rememberedEmailBanner');
    const rememberedText = document.getElementById('rememberedEmailText');
    
    // Check for saved email on load
    const params = new URLSearchParams(window.location.search);
    const urlEmail = params.get('email');
    const savedEmail = localStorage.getItem(STORAGE_KEY);
    
    if (urlEmail) {
        emailInput.value = urlEmail;
        lookupTickets(urlEmail);
    } else if (savedEmail) {
        emailInput.value = savedEmail;
        rememberedText.textContent = savedEmail;
        rememberedBanner.classList.remove('hidden');
        lookupTickets(savedEmail);
    }
    
    document.getElementById('forgetEmailBtn')?.addEventListener('click', function() {
        localStorage.removeItem(STORAGE_KEY);
        rememberedBanner.classList.add('hidden');
        emailInput.value = '';
        emailInput.focus();
    });
    
    document.getElementById('switchAccountBtn')?.addEventListener('click', function() {
        localStorage.removeItem(STORAGE_KEY);
        lookupCard.classList.remove('hidden');
        ticketsCard.classList.add('hidden');
        rememberedBanner.classList.add('hidden');
        emailInput.value = '';
        window.history.replaceState({}, '', window.location.pathname);
    });
    
    lookupForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        lookupTickets(emailInput.value);
    });
    
    async function lookupTickets(email) {
        lookupBtn.disabled = true;
        lookupBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
        
        try {
            const res = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public')); ?>?email=' + encodeURIComponent(email));
            const result = await res.json();
            const tickets = result.tickets || result || [];
            
            localStorage.setItem(STORAGE_KEY, email);
            currentEmail = email;
            
            lookupCard.classList.add('hidden');
            ticketsCard.classList.remove('hidden');
            ticketsEmailEl.textContent = email;
            
            if (!tickets || tickets.length === 0) {
                ticketsContent.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-inbox"></i></div><h3>No Tickets Found</h3><p>We couldn\'t find any support tickets for this email address.</p><a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Submit Your First Ticket</a></div>';
            } else {
                ticketsContent.innerHTML = tickets.map(t => {
                    const date = new Date(t.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    return '<div class="ticket-row" data-ticket="' + t.ticket_number + '"><div class="ticket-info"><div class="ticket-number">#' + t.ticket_number + '</div><div class="ticket-subject">' + escapeHtml(t.subject) + '</div><div class="ticket-date"><i class="fa-regular fa-clock"></i> ' + date + '</div></div><div class="ticket-status"><span class="status-badge ' + t.status + '">' + t.status + '</span></div><div class="ticket-arrow"><i class="fa-solid fa-chevron-right"></i></div></div>';
                }).join('');
                
                ticketsContent.querySelectorAll('.ticket-row').forEach(row => {
                    row.addEventListener('click', function() {
                        window.location.href = '<?php echo esc_url(oversee_support_url('tickets')); ?>/' + this.dataset.ticket + '?email=' + encodeURIComponent(currentEmail);
                    });
                });
            }
        } catch (err) {
            alert('Failed to load tickets. Please try again.');
        } finally {
            lookupBtn.disabled = false;
            lookupBtn.innerHTML = '<i class="fa-solid fa-search"></i> Find My Tickets';
        }
    }
    
    function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
})();
</script>
