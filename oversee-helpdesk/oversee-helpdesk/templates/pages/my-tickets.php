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
    const { Toast, Skeleton, EmptyState, Loading, escapeHtml: escapeHtmlUtil } = window.OverseeUX || {};

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
        const email = emailInput.value.trim();

        // Basic email validation
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            if (Toast) {
                Toast.error('Please enter a valid email address.');
            } else {
                alert('Please enter a valid email address.');
            }
            emailInput.focus();
            return;
        }

        lookupTickets(email);
    });

    async function lookupTickets(email) {
        // Show loading state on button
        if (Loading) {
            Loading.button(lookupBtn, true, 'Loading...');
        } else {
            lookupBtn.disabled = true;
            lookupBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
        }

        // Switch to tickets card and show skeleton loader
        lookupCard.classList.add('hidden');
        ticketsCard.classList.remove('hidden');
        ticketsEmailEl.textContent = email;

        // Show skeleton loaders while fetching
        if (Skeleton) {
            ticketsContent.innerHTML = Skeleton.ticketList(4);
        } else {
            ticketsContent.innerHTML = '<div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i><span>Loading tickets...</span></div>';
        }

        try {
            const res = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public')); ?>?email=' + encodeURIComponent(email));

            if (!res.ok) {
                throw new Error('Failed to fetch tickets');
            }

            const result = await res.json();
            const tickets = result.tickets || result || [];

            localStorage.setItem(STORAGE_KEY, email);
            currentEmail = email;

            if (!tickets || tickets.length === 0) {
                // Enhanced empty state
                if (EmptyState) {
                    ticketsContent.innerHTML = EmptyState.noTickets('<?php echo esc_url(oversee_support_url('submit')); ?>');
                } else {
                    ticketsContent.innerHTML = `
                        <div class="empty-state-enhanced">
                            <div class="empty-icon"><i class="fa-solid fa-ticket"></i></div>
                            <h3>No Tickets Found</h3>
                            <p>You haven't submitted any support tickets yet. We're here to help when you need us!</p>
                            <div class="empty-actions">
                                <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary">
                                    <i class="fa-solid fa-plus"></i> Submit Your First Ticket
                                </a>
                            </div>
                            <div class="empty-tip">
                                <p>Tip: Check our <a href="<?php echo esc_url(oversee_kb_url()); ?>">Knowledge Base</a> for quick answers to common questions.</p>
                            </div>
                        </div>
                    `;
                }
            } else {
                ticketsContent.innerHTML = tickets.map(t => {
                    const date = formatDate(t.created_at);
                    const safeSubject = escapeHtml(t.subject);
                    return `
                        <div class="ticket-row" data-ticket="${t.ticket_number}" role="button" tabindex="0" aria-label="View ticket #${t.ticket_number}">
                            <div class="ticket-info">
                                <div class="ticket-number">#${t.ticket_number}</div>
                                <div class="ticket-subject">${safeSubject}</div>
                                <div class="ticket-date"><i class="fa-regular fa-clock"></i> ${date}</div>
                            </div>
                            <div class="ticket-status">
                                <span class="status-badge ${t.status}">${t.status}</span>
                            </div>
                            <div class="ticket-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                        </div>
                    `;
                }).join('');

                // Add click and keyboard handlers
                ticketsContent.querySelectorAll('.ticket-row').forEach(row => {
                    const navigate = () => {
                        window.location.href = '<?php echo esc_url(oversee_support_url('tickets')); ?>/' + row.dataset.ticket + '?email=' + encodeURIComponent(currentEmail);
                    };
                    row.addEventListener('click', navigate);
                    row.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            navigate();
                        }
                    });
                });
            }
        } catch (err) {
            console.error('Ticket lookup error:', err);

            // Show error state
            if (EmptyState) {
                ticketsContent.innerHTML = EmptyState.error('Failed to load tickets. Please try again.');
            } else {
                ticketsContent.innerHTML = `
                    <div class="empty-state-enhanced">
                        <div class="empty-icon"><i class="fa-solid fa-exclamation-circle"></i></div>
                        <h3>Oops! Something went wrong</h3>
                        <p>We couldn't load your tickets. Please try again.</p>
                        <div class="empty-actions">
                            <button class="btn btn-primary" onclick="location.reload()">
                                <i class="fa-solid fa-refresh"></i> Try Again
                            </button>
                        </div>
                    </div>
                `;
            }

            if (Toast) {
                Toast.error('Failed to load tickets. Please try again.');
            }
        } finally {
            // Reset button
            if (Loading) {
                Loading.button(lookupBtn, false);
            } else {
                lookupBtn.disabled = false;
                lookupBtn.innerHTML = '<i class="fa-solid fa-search"></i> Find My Tickets';
            }
        }
    }

    function escapeHtml(t) {
        if (escapeHtmlUtil) return escapeHtmlUtil(t);
        if (!t) return '';
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
})();
</script>
