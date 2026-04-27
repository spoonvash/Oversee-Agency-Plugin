/**
 * Oversee Customer Dashboard — frontend controller.
 * Talks only to the WordPress REST namespace `ocd/v1` (server-side API tokens).
 *
 * Highlights:
 *  - Customer + admin task kanbans support drag/drop status changes.
 *  - Message threads support image attachments uploaded to private storage,
 *    rendered through the permission-checked /files/<id>/download endpoint.
 *  - The customer/admin nav was simplified to remove duplicate menus that
 *    pointed at the same data; old anchors map onto the new tab names.
 */
(function () {
    'use strict';

    var cfg = window.OCD_CONFIG || {};
    if (!cfg.restUrl) return;

    function api(path, opts) {
        opts = opts || {};
        var headers = {
            'X-WP-Nonce': cfg.nonce || ''
        };
        var body;
        if (opts.formData) {
            // Let the browser set the multipart boundary.
            body = opts.formData;
        } else if (opts.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(opts.body);
        }
        return fetch(cfg.restUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, ''), {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            headers: headers,
            body: body
        }).then(function (r) {
            return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }).catch(function () {
                return { ok: r.ok, status: r.status, body: null };
            });
        });
    }

    function fmtMoney(amount, currency) {
        if (amount === undefined || amount === null || amount === '') return '—';
        var n = parseFloat(amount);
        if (isNaN(n)) return amount;
        try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(n); }
        catch (e) { return (currency || '$') + n.toFixed(2); }
    }
    function fmtDate(s) {
        if (!s) return '—';
        var d = new Date(s);
        if (isNaN(d.getTime())) return s;
        return d.toLocaleDateString();
    }
    function fmtDateTime(s) {
        if (!s) return '—';
        var d = new Date(s.replace(' ', 'T') + (/[zZ]$|[+-]\d/.test(s) ? '' : 'Z'));
        if (isNaN(d.getTime())) return s;
        return d.toLocaleString();
    }
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }
    function badge(status) {
        var safe = (status || '').toString().toLowerCase();
        return '<span class="ocd-badge ocd-badge--' + escapeHtml(safe) + '">' + escapeHtml(status || '') + '</span>';
    }
    function setText(node, text) { if (node) node.textContent = text; }

    // Plain-language column ordering for the kanban. Mirrors
    // OCD_Tasks::status_groups() server-side.
    var KANBAN_COLUMNS_ADMIN = [
        { key: 'needs_your_input',   label: 'Needs Client Input' },
        { key: 'not_started',        label: 'Not Started' },
        { key: 'in_progress',        label: 'In Progress' },
        { key: 'waiting_on_oversee', label: 'Waiting on Oversee' },
        { key: 'blocked',            label: 'Blocked' },
        { key: 'completed',          label: 'Done' }
    ];
    var KANBAN_COLUMNS_CUSTOMER = [
        { key: 'needs_your_input',   label: 'Needs Your Input' },
        { key: 'not_started',        label: 'Not Started' },
        { key: 'in_progress',        label: 'In Progress' },
        { key: 'waiting_on_oversee', label: 'Waiting on Oversee' },
        { key: 'completed',          label: 'Done' }
    ];
    var CUSTOMER_ALLOWED = ['not_started', 'in_progress', 'completed', 'waiting_on_oversee'];

    /* ---------------- Customer ---------------- */

    function setupCustomerTabs(root) {
        var tabs = root.querySelectorAll('[data-ocd-cust-tab]');
        var panels = root.querySelectorAll('[data-ocd-cust-panel]');
        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                tabs.forEach(function (x) { x.classList.remove('is-active'); });
                panels.forEach(function (p) { p.classList.remove('is-active'); });
                t.classList.add('is-active');
                var name = t.getAttribute('data-ocd-cust-tab');
                var panel = root.querySelector('[data-ocd-cust-panel="' + name + '"]');
                if (panel) panel.classList.add('is-active');
                if (name === 'messages') loadCustomerMessages(root);
                if (name === 'projects') loadCustomerProjects(root);
                if (name === 'files')    loadCustomerFiles(root);
                if (name === 'home')     loadCustomerHomeStore(root);
            });
        });
    }

    function renderProjects(target, projects, isAdmin) {
        if (!target) return;
        if (!projects || !projects.length) {
            target.innerHTML = '<p class="ocd-empty">No projects yet.</p>';
            return;
        }
        target.innerHTML = projects.map(function (p) {
            var milestones = (p.milestones || []).map(function (m) {
                var done = m.status === 'completed';
                var img = m.instruction_image_url
                    ? '<img class="ocd-instruction-img" loading="lazy" src="' + escapeHtml(m.instruction_image_url) + '" alt="" />'
                    : '';
                return '<li class="ocd-milestone ocd-milestone--' + escapeHtml(m.status) + '">' +
                    '<span class="ocd-milestone__dot' + (done ? ' is-done' : '') + '"></span>' +
                    '<div class="ocd-milestone__body">' +
                        '<strong>' + escapeHtml(m.title) + '</strong>' +
                        (m.due_date ? ' <span class="ocd-muted">· due ' + escapeHtml(fmtDate(m.due_date)) + '</span>' : '') +
                        (m.note ? '<p class="ocd-muted">' + escapeHtml(m.note) + '</p>' : '') +
                        img +
                    '</div>' +
                    '<span class="ocd-milestone__status">' + badge(m.status) + '</span>' +
                '</li>';
            }).join('');
            var pct = parseInt(p.progress, 10) || 0;
            var adminControls = '';
            if (isAdmin) {
                adminControls = '<div class="ocd-actions">'
                    + '<button class="ocd-btn ocd-btn--ghost" data-ocd-project-edit="' + escapeHtml(p.id) + '">Edit</button>'
                    + '<button class="ocd-btn ocd-btn--ghost" data-ocd-project-milestone="' + escapeHtml(p.id) + '">Add milestone</button>'
                    + '<button class="ocd-btn ocd-btn--danger" data-ocd-project-delete="' + escapeHtml(p.id) + '">Delete</button>'
                + '</div>';
            }
            return '<article class="ocd-project">' +
                '<header class="ocd-project__head">' +
                    '<div>' +
                        '<h3>' + escapeHtml(p.title) + '</h3>' +
                        '<p class="ocd-muted">' + (p.description ? escapeHtml(p.description) : 'No description') + '</p>' +
                    '</div>' +
                    '<div class="ocd-project__meta">' +
                        badge(p.status) +
                        (p.target_date ? '<span class="ocd-muted">Target: ' + escapeHtml(fmtDate(p.target_date)) + '</span>' : '') +
                    '</div>' +
                '</header>' +
                '<div class="ocd-progress"><div class="ocd-progress__bar" style="width:' + pct + '%"></div><span class="ocd-progress__label">' + pct + '%</span></div>' +
                '<ol class="ocd-milestones">' + (milestones || '<li class="ocd-empty">No milestones yet.</li>') + '</ol>' +
                adminControls +
            '</article>';
        }).join('');
    }

    function initCustomer(root) {
        setupCustomerTabs(root);

        var subsBody = root.querySelector('[data-ocd-list="subscriptions"]');
        var ordersList = root.querySelector('[data-ocd-list="orders"]');

        api('customer/dashboard').then(function (res) {
            if (!res.ok) {
                if (subsBody) subsBody.innerHTML = '<tr><td colspan="5" class="ocd-empty">' + escapeHtml((res.body && res.body.message) || 'Failed to load.') + '</td></tr>';
                return;
            }
            var data = res.body || {};
            var subs = data.subscriptions || [];
            var orders = data.orders || [];
            var projects = data.projects || [];
            var tasks = data.tasks || [];

            var activeCount = subs.filter(function (s) { return s.status === 'active'; }).length;
            var openProjects = projects.filter(function (p) { return p.status !== 'completed' && p.status !== 'cancelled'; }).length;
            var openTasks = tasks.filter(function (t) { return t.status !== 'completed' && t.status !== 'cancelled'; }).length;
            setText(root.querySelector('[data-ocd-stat="active-subs"]'), activeCount);
            setText(root.querySelector('[data-ocd-stat="open-projects"]'), openProjects);
            setText(root.querySelector('[data-ocd-stat="open-tasks"]'), openTasks);
            setText(root.querySelector('[data-ocd-stat="total-orders"]'), orders.length);

            if (subsBody) {
                if (!subs.length) {
                    subsBody.innerHTML = '<tr><td colspan="5" class="ocd-empty">' + (data.connections && data.connections.woocommerce ? 'No subscriptions found.' : 'WooCommerce not connected.') + '</td></tr>';
                } else {
                    subsBody.innerHTML = subs.map(function (s) {
                        var canCancel = (s.status === 'active' || s.status === 'on-hold');
                        return '<tr>' +
                            '<td>#' + escapeHtml(s.id) + '</td>' +
                            '<td>' + badge(s.status) + '</td>' +
                            '<td>' + escapeHtml(fmtMoney(s.total, s.currency)) + '</td>' +
                            '<td>' + escapeHtml(fmtDate(s.next_payment_date_gmt || s.next_payment_date)) + '</td>' +
                            '<td>' + (canCancel
                                ? '<button class="ocd-btn ocd-btn--danger" data-ocd-cancel="' + escapeHtml(s.id) + '">Cancel</button>'
                                : '<span class="ocd-muted">—</span>') +
                            '</td>' +
                        '</tr>';
                    }).join('');
                }
            }
            if (ordersList) {
                if (!orders.length) {
                    ordersList.innerHTML = '<li class="ocd-empty">' + (data.connections && data.connections.woocommerce ? 'No recent orders.' : 'WooCommerce not connected.') + '</li>';
                } else {
                    ordersList.innerHTML = orders.slice(0, 8).map(function (o) {
                        return '<li>' +
                            '<span><strong>#' + escapeHtml(o.number || o.id) + '</strong> — ' + escapeHtml(fmtDate(o.date_created_gmt || o.date_created)) + ' &middot; ' + badge(o.status) + '</span>' +
                            '<span class="ocd-muted">' + escapeHtml(fmtMoney(o.total, o.currency)) + '</span>' +
                        '</li>';
                    }).join('');
                }
            }

            renderProjects(root.querySelector('[data-ocd-list="projects"]'), projects, false);
            renderCustomerKanban(root, tasks);
            renderCustomerFiles(root.querySelector('[data-ocd-list="files"]'), data.files || []);
            loadCustomerHomeStore(root);
            loadCustomerCRM(root);

            root.querySelectorAll('[data-ocd-cancel]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-ocd-cancel');
                    if (!confirm('Request cancellation of subscription #' + id + '?')) return;
                    btn.disabled = true; btn.textContent = 'Cancelling…';
                    api('customer/subscriptions/' + id + '/cancel', { method: 'POST' }).then(function (r) {
                        if (r.ok) { btn.textContent = 'Pending cancel'; btn.classList.add('is-disabled'); }
                        else { btn.disabled = false; btn.textContent = 'Cancel'; alert((r.body && r.body.message) || 'Failed.'); }
                    });
                });
            });
        });

        wireMessageForm(root, root.querySelector('[data-ocd-form="send-message"]'), {
            uploadPath: 'customer/messages/attachments',
            sendPath:   'customer/messages',
            onSent:     function () { loadCustomerMessages(root); }
        });

        var crmForm = root.querySelector('[data-ocd-form="connect-crm"]');
        if (crmForm) {
            crmForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(crmForm);
                var body = {};
                fd.forEach(function (v, k) { body[k] = v; });
                if (!body.access_token) return;
                api('customer/crm', { method: 'POST', body: body }).then(function (r) {
                    if (r.ok) loadCustomerCRM(root);
                    else alert((r.body && r.body.message) || 'Failed.');
                });
            });
        }
    }

    /* ---------------- Kanban (shared customer/admin) ---------------- */

    function renderCustomerKanban(root, tasks) {
        var board = root.querySelector('[data-ocd-kanban="customer"]');
        if (!board) return;
        if (!tasks || !tasks.length) {
            board.innerHTML = '<p class="ocd-empty">No tasks assigned. You\'re all caught up!</p>';
            return;
        }
        var grouped = {};
        KANBAN_COLUMNS_CUSTOMER.forEach(function (c) { grouped[c.key] = []; });
        (tasks || []).forEach(function (t) {
            var status = t.status;
            if (!grouped[status]) status = 'completed';
            grouped[status].push(t);
        });
        board.innerHTML = KANBAN_COLUMNS_CUSTOMER.map(function (col) {
            return renderKanbanColumn(col, grouped[col.key] || [], 'customer');
        }).join('');
        wireKanban(root, board, 'customer');
    }

    function renderKanbanColumn(col, items, role) {
        return '<div class="ocd-kanban__col" data-ocd-kanban-col="' + escapeHtml(col.key) + '">' +
            '<div class="ocd-kanban__col-head">' +
                '<span>' + escapeHtml(col.label) + '</span>' +
                '<span class="ocd-pill">' + items.length + '</span>' +
            '</div>' +
            '<div class="ocd-kanban__col-body" data-ocd-kanban-drop="' + escapeHtml(col.key) + '">' +
                (items.length ? items.map(function (t) { return renderKanbanCard(t, role); }).join('') : '<p class="ocd-empty ocd-kanban__col-empty">—</p>') +
            '</div>' +
        '</div>';
    }

    function renderKanbanCard(t, role) {
        var dueRow = t.due_date ? '<div class="ocd-task-card__row ocd-muted">Due ' + escapeHtml(fmtDate(t.due_date)) + '</div>' : '';
        var attachments = renderTaskAttachmentStrip(t);
        var instructionImg = t.instruction_image_url
            ? '<img class="ocd-task-card__hero" loading="lazy" src="' + escapeHtml(t.instruction_image_url) + '" alt="" />'
            : '';
        var actions = '';
        if (role === 'admin') {
            actions = '<div class="ocd-actions ocd-actions--inline">' +
                '<button class="ocd-btn ocd-btn--ghost ocd-btn--xs" data-ocd-task-attach="' + escapeHtml(t.id) + '">Attach image</button>' +
                '<button class="ocd-btn ocd-btn--danger ocd-btn--xs" data-ocd-task-del="' + escapeHtml(t.id) + '">Delete</button>' +
            '</div>';
        }
        return '<div class="ocd-task-card" draggable="true" data-ocd-task-card="' + escapeHtml(t.id) + '" data-ocd-task-status="' + escapeHtml(t.status) + '">' +
            instructionImg +
            '<div class="ocd-task-card__title">' + escapeHtml(t.title) + '</div>' +
            (t.details ? '<div class="ocd-task-card__details ocd-muted">' + escapeHtml(t.details) + '</div>' : '') +
            attachments +
            dueRow +
            actions +
        '</div>';
    }

    function renderTaskAttachmentStrip(t) {
        var atts = (t && t.attachments) || [];
        if (!atts.length) return '';
        return '<div class="ocd-task-card__attachments">' + atts.map(function (a) {
            if (a.is_image) {
                return '<a class="ocd-thumb" href="' + escapeHtml(a.download_url) + '" target="_blank" rel="noopener">' +
                    '<img loading="lazy" src="' + escapeHtml(a.download_url) + '" alt="' + escapeHtml(a.file_name) + '" />' +
                '</a>';
            }
            return '<a class="ocd-file-pill" href="' + escapeHtml(a.download_url) + '" target="_blank" rel="noopener">📎 ' + escapeHtml(a.file_name) + '</a>';
        }).join('') + '</div>';
    }

    function wireKanban(root, board, role) {
        var dragging = null;
        board.querySelectorAll('[data-ocd-task-card]').forEach(function (card) {
            card.addEventListener('dragstart', function (ev) {
                dragging = card;
                card.classList.add('is-dragging');
                if (ev.dataTransfer) {
                    ev.dataTransfer.effectAllowed = 'move';
                    ev.dataTransfer.setData('text/plain', card.getAttribute('data-ocd-task-card'));
                }
            });
            card.addEventListener('dragend', function () {
                if (dragging) dragging.classList.remove('is-dragging');
                dragging = null;
            });
        });
        board.querySelectorAll('[data-ocd-kanban-drop]').forEach(function (col) {
            col.addEventListener('dragover', function (ev) {
                ev.preventDefault();
                col.classList.add('is-drop-target');
                if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move';
            });
            col.addEventListener('dragleave', function () { col.classList.remove('is-drop-target'); });
            col.addEventListener('drop', function (ev) {
                ev.preventDefault();
                col.classList.remove('is-drop-target');
                if (!dragging) return;
                var newStatus = col.getAttribute('data-ocd-kanban-drop');
                var taskId = dragging.getAttribute('data-ocd-task-card');
                var oldStatus = dragging.getAttribute('data-ocd-task-status');
                if (newStatus === oldStatus) return;
                if (role === 'customer' && CUSTOMER_ALLOWED.indexOf(newStatus) === -1) {
                    flashError(dragging, 'Customers can\'t set this status.');
                    return;
                }
                col.appendChild(dragging);
                dragging.classList.add('is-saving');
                var path = role === 'admin'
                    ? 'admin/tasks/' + taskId + '/status'
                    : 'customer/tasks/' + taskId + '/status';
                api(path, { method: 'POST', body: { status: newStatus } }).then(function (r) {
                    if (dragging) dragging.classList.remove('is-saving');
                    if (r.ok) {
                        if (dragging) dragging.setAttribute('data-ocd-task-status', newStatus);
                        if (role === 'admin') loadAdminTasks(root);
                        else loadCustomerTasks(root);
                    } else {
                        if (dragging) flashError(dragging, (r.body && r.body.message) || 'Failed to update status.');
                        if (role === 'admin') loadAdminTasks(root);
                        else loadCustomerTasks(root);
                    }
                });
            });
        });
    }

    function flashError(node, msg) {
        node.classList.add('is-error');
        setTimeout(function () { node.classList.remove('is-error'); }, 2000);
        if (msg) console.warn('[OCD] ' + msg);
    }

    function loadCustomerTasks(root) {
        api('customer/dashboard').then(function (r) {
            if (!r.ok) return;
            renderCustomerKanban(root, (r.body && r.body.tasks) || []);
        });
    }

    /* ---------------- Customer messages ---------------- */

    function loadCustomerMessages(root) {
        var thread = root.querySelector('[data-ocd-list="messages"]');
        var status = root.querySelector('[data-ocd-msg-status]');
        if (!thread) return;
        thread.innerHTML = '<p class="ocd-empty">Loading…</p>';
        api('customer/messages').then(function (r) {
            if (!r.ok) {
                thread.innerHTML = '<p class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>';
                return;
            }
            var msgs = (r.body && r.body.messages) || [];
            var connected = !!(r.body && r.body.connected_to_agency_crm);
            if (status) status.textContent = connected ? 'Synced with Oversee CRM' : 'Local-only — agency CRM not connected yet';
            if (!msgs.length) {
                thread.innerHTML = '<p class="ocd-empty">No messages yet. Say hello!</p>';
                return;
            }
            thread.innerHTML = msgs.map(function (m) {
                var cls = m.direction === 'inbound' ? 'ocd-msg ocd-msg--out' : 'ocd-msg ocd-msg--in';
                var label = m.direction === 'inbound' ? 'You' : 'Oversee';
                return '<div class="' + cls + '"><div class="ocd-msg__meta"><strong>' + label + '</strong> · ' + escapeHtml(fmtDateTime(m.created_at)) + '</div>' +
                    (m.body ? '<div class="ocd-msg__body">' + escapeHtml(m.body).replace(/\n/g, '<br>') + '</div>' : '') +
                    renderMessageAttachments(m.attachments) +
                '</div>';
            }).join('');
            thread.scrollTop = thread.scrollHeight;
        });
    }

    function renderMessageAttachments(attachments) {
        if (!attachments || !attachments.length) return '';
        return '<div class="ocd-msg__attachments">' + attachments.map(function (a) {
            if (a.is_image) {
                return '<a class="ocd-thumb" href="' + escapeHtml(a.download_url) + '" target="_blank" rel="noopener">' +
                    '<img loading="lazy" src="' + escapeHtml(a.download_url) + '" alt="' + escapeHtml(a.file_name) + '" />' +
                '</a>';
            }
            return '<a class="ocd-file-pill" href="' + escapeHtml(a.download_url) + '" target="_blank" rel="noopener">📎 ' + escapeHtml(a.file_name) + '</a>';
        }).join('') + '</div>';
    }

    function loadCustomerProjects(root) {
        var target = root.querySelector('[data-ocd-list="projects"]');
        if (!target) return;
        target.innerHTML = '<p class="ocd-empty">Loading…</p>';
        api('customer/projects').then(function (r) {
            if (!r.ok) { target.innerHTML = '<p class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>'; return; }
            renderProjects(target, r.body || [], false);
        });
    }

    function renderCustomerFiles(target, files) {
        if (!target) return;
        if (!files || !files.length) {
            target.innerHTML = '<p class="ocd-empty">No files yet.</p>';
            return;
        }
        target.innerHTML = '<div class="ocd-file-grid">' + files.map(function (f) {
            if (f.is_image) {
                return '<a class="ocd-file-card ocd-file-card--image" href="' + escapeHtml(f.download_url) + '" target="_blank" rel="noopener">' +
                    '<img loading="lazy" src="' + escapeHtml(f.download_url) + '" alt="' + escapeHtml(f.file_name) + '" />' +
                    '<div class="ocd-file-card__meta"><strong>' + escapeHtml(f.file_name) + '</strong>' +
                    '<span class="ocd-muted">' + escapeHtml(fmtDateTime(f.created_at)) + '</span></div>' +
                '</a>';
            }
            return '<a class="ocd-file-card" href="' + escapeHtml(f.download_url) + '" target="_blank" rel="noopener">' +
                '<div class="ocd-file-card__icon">📄</div>' +
                '<div class="ocd-file-card__meta"><strong>' + escapeHtml(f.file_name) + '</strong>' +
                '<span class="ocd-muted">' + escapeHtml(fmtDateTime(f.created_at)) + '</span></div>' +
            '</a>';
        }).join('') + '</div>';
    }

    function loadCustomerFiles(root) {
        var target = root.querySelector('[data-ocd-list="files"]');
        if (!target) return;
        target.innerHTML = '<p class="ocd-empty">Loading…</p>';
        api('customer/files').then(function (r) {
            if (!r.ok) { target.innerHTML = '<p class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>'; return; }
            renderCustomerFiles(target, r.body || []);
        });
    }

    function loadCustomerHomeStore(root) {
        var target = root.querySelector('[data-ocd-list="store"]');
        var cartBtn = root.querySelector('[data-ocd-store-cart]');
        if (!target) return;
        target.innerHTML = '<p class="ocd-empty">Loading…</p>';
        api('customer/store').then(function (r) {
            if (!r.ok) {
                target.innerHTML = '<p class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>';
                return;
            }
            var data = r.body || {};
            if (cartBtn && data.cart_url) {
                cartBtn.href = data.cart_url;
                cartBtn.hidden = false;
            }
            if (data.available === false || !data.listings || !data.listings.length) {
                var reason = data.reason || (data.wc_active === false
                    ? 'WooCommerce products unavailable.'
                    : 'No eligible WooCommerce products mapped yet.');
                target.innerHTML = '<div class="ocd-store__empty"><p class="ocd-muted">' + escapeHtml(reason) + '</p></div>';
                return;
            }
            var listings = data.listings;
            target.innerHTML = '<div class="ocd-store__grid">' + listings.map(function (it) {
                var owned = it.owned_status === 'active';
                var primaryCta = owned
                    ? '<span class="ocd-pill ocd-pill--success">Active</span>'
                    : (it.available
                        ? '<a class="ocd-btn ocd-btn--primary" href="' + escapeHtml(it.add_to_cart) + '">Add to cart</a>'
                        : '<span class="ocd-muted">Out of stock</span>');
                var checkoutCta = (!owned && it.available && data.checkout_url)
                    ? ' <a class="ocd-btn ocd-btn--ghost" href="' + escapeHtml(data.checkout_url) + '">Checkout</a>'
                    : '';
                var img = it.image
                    ? '<div class="ocd-store__image"><img loading="lazy" alt="" src="' + escapeHtml(it.image) + '" /></div>'
                    : '';
                return '<div class="ocd-store__card">' +
                    img +
                    '<h3>' + escapeHtml(it.name || it.label) + '</h3>' +
                    (it.description ? '<p class="ocd-muted">' + escapeHtml(it.description) + '</p>' : '') +
                    (it.price_html ? '<div class="ocd-store__price">' + it.price_html + '</div>' : '') +
                    '<div class="ocd-actions">' + primaryCta + checkoutCta +
                        (it.permalink ? ' <a class="ocd-btn ocd-btn--ghost" href="' + escapeHtml(it.permalink) + '">Details</a>' : '') +
                    '</div>' +
                '</div>';
            }).join('') + '</div>';
        });
    }

    function loadCustomerCRM(root) {
        var block = root.querySelector('[data-ocd-block="customer-crm"]');
        var form  = root.querySelector('[data-ocd-form="connect-crm"]');
        if (!block) return;
        block.innerHTML = '<p class="ocd-muted">Loading…</p>';
        api('customer/crm').then(function (r) {
            if (!r.ok) { block.innerHTML = '<p class="ocd-muted">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>'; return; }
            var d = r.body || { connected: false };
            if (d.connected) {
                block.innerHTML = '<p>Connected to <strong>' + escapeHtml(d.label || d.provider) + '</strong>' +
                    (d.location_id ? ' · location <code>' + escapeHtml(d.location_id) + '</code>' : '') + '</p>' +
                    '<p class="ocd-muted">Linked ' + escapeHtml(fmtDateTime(d.connected_at)) + '</p>' +
                    '<button class="ocd-btn ocd-btn--danger" data-ocd-action="disconnect-crm">Disconnect</button>';
                if (form) form.hidden = true;
                var dis = block.querySelector('[data-ocd-action="disconnect-crm"]');
                if (dis) dis.addEventListener('click', function () {
                    if (!confirm('Disconnect your CRM?')) return;
                    api('customer/crm', { method: 'DELETE' }).then(function () { loadCustomerCRM(root); });
                });
            } else {
                block.innerHTML = '<p class="ocd-muted">No customer-owned CRM connected.</p>';
                if (form) form.hidden = false;
            }
        });
    }

    /* ---------------- Message form (shared) ----------------
     *
     * Two-step flow: (1) for each selected file, POST to opts.uploadPath as
     * multipart; the server stores it in private storage and returns its
     * file id. (2) on submit, POST to opts.sendPath with body+attachment_ids.
     * If any upload fails, send is blocked until the user removes the
     * failed preview.
     */
    function wireMessageForm(root, form, opts) {
        if (!form) return;
        // Clone the form first so any previously-attached handlers (e.g. from
        // a prior thread open) are dropped — this is what lets the admin
        // reply form switch between threads cleanly.
        var fresh = form.cloneNode(true);
        form.parentNode.replaceChild(fresh, form);
        form = fresh;

        var fileInput = form.querySelector('input[type="file"]');
        var previews  = form.querySelector('[data-ocd-msg-previews]');
        var pending = [];

        function renderPreviews() {
            if (!previews) return;
            previews.innerHTML = pending.map(function (p) {
                var stateLabel = p.status === 'uploading' ? '⏳' : (p.status === 'error' ? '⚠' : '🖼');
                return '<span class="ocd-msg-preview ocd-msg-preview--' + p.status + '" data-ocd-preview="' + p.tempId + '">' +
                    stateLabel + ' ' + escapeHtml(p.name) +
                    ' <button type="button" class="ocd-msg-preview__remove" aria-label="Remove" data-ocd-preview-remove="' + p.tempId + '">×</button>' +
                '</span>';
            }).join('');
            previews.querySelectorAll('[data-ocd-preview-remove]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-ocd-preview-remove');
                    pending = pending.filter(function (x) { return x.tempId !== id; });
                    renderPreviews();
                });
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var files = Array.from(fileInput.files || []);
                fileInput.value = '';
                files.forEach(function (file) {
                    var tempId = 'p_' + Math.random().toString(36).slice(2);
                    var entry = { tempId: tempId, status: 'uploading', name: file.name };
                    pending.push(entry);
                    renderPreviews();
                    var fd = new FormData();
                    fd.append('file', file);
                    api(opts.uploadPath, { method: 'POST', formData: fd }).then(function (r) {
                        if (r.ok && r.body && r.body.id) {
                            entry.status = 'done';
                            entry.fileId = parseInt(r.body.id, 10);
                        } else {
                            entry.status = 'error';
                        }
                        renderPreviews();
                    });
                });
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var ta = form.querySelector('textarea[name="message"]');
            var btn = form.querySelector('button[type="submit"]');
            var msg = ta ? ta.value.trim() : '';
            if (pending.some(function (p) { return p.status === 'error'; })) {
                alert('Remove the failed attachment before sending.');
                return;
            }
            if (pending.some(function (p) { return p.status === 'uploading'; })) {
                alert('Wait for attachments to finish uploading.');
                return;
            }
            var fileIds = pending.filter(function (p) { return p.status === 'done'; }).map(function (p) { return p.fileId; });
            if (!msg && !fileIds.length) return;
            if (btn) btn.disabled = true;
            api(opts.sendPath, { method: 'POST', body: { message: msg, attachment_ids: fileIds } }).then(function (r) {
                if (btn) btn.disabled = false;
                if (r.ok) {
                    if (ta) ta.value = '';
                    pending = [];
                    renderPreviews();
                    if (typeof opts.onSent === 'function') opts.onSent();
                } else {
                    alert((r.body && r.body.message) || 'Failed to send.');
                }
            });
        });
    }

    /* ---------------- Admin ---------------- */

    function initAdmin(root) {
        var tabs = root.querySelectorAll('[data-ocd-tab]');
        var panels = root.querySelectorAll('[data-ocd-panel]');
        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                tabs.forEach(function (x) { x.classList.remove('is-active'); });
                panels.forEach(function (p) { p.classList.remove('is-active'); });
                t.classList.add('is-active');
                var name = t.getAttribute('data-ocd-tab');
                var panel = root.querySelector('[data-ocd-panel="' + name + '"]');
                if (panel) panel.classList.add('is-active');
                if (name === 'inbox')    loadAdminInbox(root);
                if (name === 'work')     { loadAdminTasks(root); loadAdminProjects(root); }
                if (name === 'clients')  { loadCustomers(root); loadContacts(root); loadOpportunities(root); }
                if (name === 'billing')  { loadSubscriptions(root); loadProductMap(root); }
                if (name === 'settings') { loadSync(root); loadStoreCategory(root); }
            });
        });

        function loadOverview() {
            var body = root.querySelector('[data-ocd-list="admin-recent-subs"]');
            api('admin/subscriptions?per_page=10').then(function (r) {
                if (!r.ok) {
                    if (body) body.innerHTML = '<tr><td colspan="5" class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Not configured.') + '</td></tr>';
                    return;
                }
                var subs = r.body || [];
                ['active', 'on-hold', 'pending-cancel', 'cancelled'].forEach(function (st) {
                    var cell = root.querySelector('[data-ocd-admin-stat="' + st + '"]');
                    if (cell) cell.textContent = subs.filter(function (s) { return s.status === st; }).length;
                });
                if (body) {
                    body.innerHTML = subs.length ? subs.map(function (s) {
                        var customer = ((s.billing && (s.billing.first_name || s.billing.last_name)) ? (s.billing.first_name + ' ' + s.billing.last_name) : (s.billing && s.billing.email)) || ('#' + s.customer_id);
                        return '<tr><td>#' + escapeHtml(s.id) + '</td><td>' + escapeHtml(customer) + '</td><td>' + badge(s.status) + '</td><td>' + escapeHtml(fmtMoney(s.total, s.currency)) + '</td><td>' + escapeHtml(fmtDate(s.next_payment_date_gmt || s.next_payment_date)) + '</td></tr>';
                    }).join('') : '<tr><td colspan="5" class="ocd-empty">No subscriptions.</td></tr>';
                }
            });
        }
        loadOverview();

        var refreshInbox = root.querySelector('[data-ocd-action="refresh-inbox"]');
        if (refreshInbox) refreshInbox.addEventListener('click', function () { loadAdminInbox(root); });

        var newProject = root.querySelector('[data-ocd-action="new-project"]');
        if (newProject) newProject.addEventListener('click', function () {
            var userId = prompt('Customer user ID?'); if (!userId) return;
            var title  = prompt('Project title?'); if (!title) return;
            api('admin/projects', { method: 'POST', body: { user_id: parseInt(userId, 10), title: title } }).then(function (r) {
                if (r.ok) loadAdminProjects(root);
                else alert((r.body && r.body.message) || 'Failed.');
            });
        });

        var newTask = root.querySelector('[data-ocd-action="new-task"]');
        if (newTask) newTask.addEventListener('click', function () {
            var userId = prompt('Customer user ID?'); if (!userId) return;
            var title  = prompt('Task title?'); if (!title) return;
            var due    = prompt('Due date (YYYY-MM-DD, optional)?') || '';
            api('admin/tasks', { method: 'POST', body: { user_id: parseInt(userId, 10), title: title, due_date: due, visibility: 'client', task_type: 'client_required' } }).then(function (r) {
                if (r.ok) loadAdminTasks(root);
                else alert((r.body && r.body.message) || 'Failed.');
            });
        });

        var prodSearch = root.querySelector('[data-ocd-search="wc-products"]');
        var prodResults = root.querySelector('[data-ocd-list="wc-products"]');
        if (prodSearch && prodResults) {
            var ptimer;
            prodSearch.addEventListener('input', function () {
                clearTimeout(ptimer);
                var q = prodSearch.value;
                if (!q || q.length < 2) {
                    prodResults.hidden = true;
                    prodResults.innerHTML = '';
                    return;
                }
                ptimer = setTimeout(function () { searchExistingProducts(root, q); }, 250);
            });
            prodSearch.addEventListener('blur', function () {
                setTimeout(function () { prodResults.hidden = true; }, 200);
            });
        }

        var custSearch = root.querySelector('[data-ocd-search="customers"]');
        if (custSearch) {
            var ct;
            custSearch.addEventListener('input', function () {
                clearTimeout(ct);
                ct = setTimeout(function () { loadCustomers(root, custSearch.value); }, 300);
            });
        }

        var statusSel = root.querySelector('[data-ocd-filter="sub-status"]');
        if (statusSel) statusSel.addEventListener('change', function () { loadSubscriptions(root); });

        var contactSearch = root.querySelector('[data-ocd-search="contacts"]');
        if (contactSearch) {
            var ct2;
            contactSearch.addEventListener('input', function () {
                clearTimeout(ct2);
                ct2 = setTimeout(function () { loadContacts(root, contactSearch.value); }, 350);
            });
        }
        var refreshSync = root.querySelector('[data-ocd-action="refresh-sync"]');
        if (refreshSync) refreshSync.addEventListener('click', function () { loadSync(root); });
    }

    function loadAdminInbox(root) {
        var list = root.querySelector('[data-ocd-list="admin-inbox"]');
        if (!list) return;
        list.innerHTML = '<li class="ocd-empty">Loading…</li>';
        api('admin/messages/inbox').then(function (r) {
            if (!r.ok) { list.innerHTML = '<li class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</li>'; return; }
            var rows = r.body || [];
            if (!rows.length) { list.innerHTML = '<li class="ocd-empty">No conversations yet.</li>'; return; }
            list.innerHTML = rows.map(function (row) {
                return '<li class="ocd-inbox-row' + (row.unread ? ' has-unread' : '') + '" data-ocd-thread-open="' + escapeHtml(row.user_id) + '">' +
                    '<div><strong>' + escapeHtml(row.name || row.email) + '</strong>' +
                        (row.unread ? ' <span class="ocd-pill ocd-pill--unread">' + row.unread + '</span>' : '') +
                    '</div>' +
                    '<div class="ocd-muted">' + escapeHtml(row.last_preview) + '</div>' +
                    '<div class="ocd-muted ocd-inbox-row__date">' + escapeHtml(fmtDateTime(row.last_at)) + '</div>' +
                '</li>';
            }).join('');
            list.querySelectorAll('[data-ocd-thread-open]').forEach(function (li) {
                li.addEventListener('click', function () {
                    var uid = li.getAttribute('data-ocd-thread-open');
                    list.querySelectorAll('.ocd-inbox-row').forEach(function (x) { x.classList.remove('is-active'); });
                    li.classList.add('is-active');
                    openThread(root, uid);
                });
            });
        });
    }

    function openThread(root, userId) {
        var head = root.querySelector('[data-ocd-block="thread-head"]');
        var msgs = root.querySelector('[data-ocd-list="admin-thread"]');
        var form = root.querySelector('[data-ocd-form="admin-reply"]');
        if (!msgs) return;
        msgs.innerHTML = '<p class="ocd-empty">Loading…</p>';
        api('admin/messages/thread/' + userId).then(function (r) {
            if (!r.ok) { msgs.innerHTML = '<p class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>'; return; }
            var d = r.body || {};
            if (head) head.innerHTML = '<h3>' + escapeHtml(d.name || d.email) + '</h3><p class="ocd-muted">' + escapeHtml(d.email || '') + '</p>';
            var thread = d.messages || [];
            if (!thread.length) {
                msgs.innerHTML = '<p class="ocd-empty">No messages yet.</p>';
            } else {
                msgs.innerHTML = thread.map(function (m) {
                    var cls = m.direction === 'inbound' ? 'ocd-msg ocd-msg--in' : 'ocd-msg ocd-msg--out';
                    var label = m.direction === 'inbound' ? (d.name || 'Customer') : 'Oversee';
                    return '<div class="' + cls + '"><div class="ocd-msg__meta"><strong>' + escapeHtml(label) + '</strong> · ' + escapeHtml(fmtDateTime(m.created_at)) + (m.hl_synced ? ' · <span class="ocd-pill ocd-pill--success">CRM ✓</span>' : '') + '</div>' +
                        (m.body ? '<div class="ocd-msg__body">' + escapeHtml(m.body).replace(/\n/g, '<br>') + '</div>' : '') +
                        renderMessageAttachments(m.attachments) +
                    '</div>';
                }).join('');
                msgs.scrollTop = msgs.scrollHeight;
            }
            if (form) {
                form.hidden = false;
                wireMessageForm(root, form, {
                    uploadPath: 'admin/messages/thread/' + userId + '/attachments',
                    sendPath:   'admin/messages/thread/' + userId,
                    onSent:     function () { openThread(root, userId); }
                });
            }
        });
    }

    function loadAdminProjects(root) {
        var target = root.querySelector('[data-ocd-list="admin-projects"]');
        if (!target) return;
        target.innerHTML = '<p class="ocd-empty">Loading…</p>';
        api('admin/projects').then(function (r) {
            if (!r.ok) { target.innerHTML = '<p class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>'; return; }
            var projects = r.body || [];
            renderProjects(target, projects, true);
            target.querySelectorAll('[data-ocd-project-edit]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var id = b.getAttribute('data-ocd-project-edit');
                    var status = prompt('New status (planning/in-progress/on-hold/review/completed/cancelled)?');
                    if (!status) return;
                    api('admin/projects/' + id, { method: 'POST', body: { status: status } }).then(function (rr) {
                        if (rr.ok) loadAdminProjects(root);
                        else alert((rr.body && rr.body.message) || 'Failed.');
                    });
                });
            });
            target.querySelectorAll('[data-ocd-project-milestone]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var id = b.getAttribute('data-ocd-project-milestone');
                    var title = prompt('Milestone title?'); if (!title) return;
                    var due = prompt('Due date (YYYY-MM-DD, optional)?') || '';
                    api('admin/projects/' + id + '/milestones', { method: 'POST', body: { title: title, due_date: due } }).then(function (rr) {
                        if (rr.ok) loadAdminProjects(root);
                        else alert((rr.body && rr.body.message) || 'Failed.');
                    });
                });
            });
            target.querySelectorAll('[data-ocd-project-delete]').forEach(function (b) {
                b.addEventListener('click', function () {
                    if (!confirm('Delete this project?')) return;
                    api('admin/projects/' + b.getAttribute('data-ocd-project-delete'), { method: 'DELETE' }).then(function (rr) {
                        if (rr.ok) loadAdminProjects(root);
                    });
                });
            });
        });
    }

    function loadAdminTasks(root) {
        var board = root.querySelector('[data-ocd-kanban="admin"]');
        if (!board) return;
        board.innerHTML = '<p class="ocd-empty">Loading…</p>';
        api('admin/tasks?per_page=200').then(function (r) {
            if (!r.ok) { board.innerHTML = '<p class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>'; return; }
            var rows = r.body || [];
            if (!rows.length) {
                board.innerHTML = '<p class="ocd-empty">No tasks yet.</p>';
                return;
            }
            var grouped = {};
            KANBAN_COLUMNS_ADMIN.forEach(function (c) { grouped[c.key] = []; });
            rows.forEach(function (t) {
                var status = t.status;
                if (!grouped[status]) status = 'completed';
                grouped[status].push(t);
            });
            board.innerHTML = KANBAN_COLUMNS_ADMIN.map(function (col) {
                return renderKanbanColumn(col, grouped[col.key] || [], 'admin');
            }).join('');
            wireKanban(root, board, 'admin');
            board.querySelectorAll('[data-ocd-task-del]').forEach(function (b) {
                b.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    if (!confirm('Delete this task?')) return;
                    api('admin/tasks/' + b.getAttribute('data-ocd-task-del'), { method: 'DELETE' }).then(function () { loadAdminTasks(root); });
                });
            });
            board.querySelectorAll('[data-ocd-task-attach]').forEach(function (b) {
                b.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    var taskId = b.getAttribute('data-ocd-task-attach');
                    var input = document.createElement('input');
                    input.type = 'file';
                    input.accept = 'image/png,image/jpeg,image/gif,image/webp';
                    input.addEventListener('change', function () {
                        if (!input.files || !input.files[0]) return;
                        var fd = new FormData();
                        fd.append('file', input.files[0]);
                        api('admin/tasks/' + taskId + '/attachments', { method: 'POST', formData: fd }).then(function (r) {
                            if (r.ok) loadAdminTasks(root);
                            else alert((r.body && r.body.message) || 'Failed.');
                        });
                    });
                    input.click();
                });
            });
        });
    }

    function loadProductMap(root) {
        var body = root.querySelector('[data-ocd-list="admin-product-map"]');
        if (!body) return;
        body.innerHTML = '<tr><td colspan="5" class="ocd-empty">Loading…</td></tr>';
        api('admin/product-map').then(function (r) {
            if (!r.ok) { body.innerHTML = '<tr><td colspan="5" class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</td></tr>'; return; }
            var map = r.body || {};
            var keys = Object.keys(map);
            if (!keys.length) {
                body.innerHTML = '<tr><td colspan="5" class="ocd-empty">No mappings yet. Use the search above to map an existing WooCommerce product to a dashboard feature.</td></tr>';
            } else {
                body.innerHTML = keys.map(function (k) {
                    var entry = map[k] || {};
                    return '<tr data-ocd-map-row="' + escapeHtml(k) + '">' +
                        '<td><img class="ocd-map-thumb" data-ocd-map-thumb="' + escapeHtml(k) + '" alt="" /></td>' +
                        '<td><strong data-ocd-map-name="' + escapeHtml(k) + '">#' + escapeHtml(k) + '</strong></td>' +
                        '<td><input class="ocd-input ocd-input--mono" data-ocd-map-slug="' + escapeHtml(k) + '" value="' + escapeHtml(entry.slug || '') + '" /></td>' +
                        '<td><input class="ocd-input" data-ocd-map-label="' + escapeHtml(k) + '" value="' + escapeHtml(entry.label || '') + '" /></td>' +
                        '<td>' +
                            '<button class="ocd-btn ocd-btn--ghost" data-ocd-map-save="' + escapeHtml(k) + '">Save</button> ' +
                            '<button class="ocd-btn ocd-btn--danger" data-ocd-map-del="' + escapeHtml(k) + '">Remove</button>' +
                        '</td>' +
                    '</tr>';
                }).join('');
                keys.forEach(function (pid) { hydrateMappedProduct(root, pid); });
                body.querySelectorAll('[data-ocd-map-save]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        var pid = b.getAttribute('data-ocd-map-save');
                        var slug = root.querySelector('[data-ocd-map-slug="' + pid + '"]').value;
                        var label = root.querySelector('[data-ocd-map-label="' + pid + '"]').value;
                        getMapAndUpdate(root, function (m) {
                            m[pid] = { slug: slug, label: label || slug };
                            return m;
                        });
                    });
                });
                body.querySelectorAll('[data-ocd-map-del]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        var pid = b.getAttribute('data-ocd-map-del');
                        if (!confirm('Remove mapping for product #' + pid + '?')) return;
                        getMapAndUpdate(root, function (m) { delete m[pid]; return m; });
                    });
                });
            }
        });
    }

    function hydrateMappedProduct(root, productId) {
        api('admin/wc-products?per_page=1&search=' + encodeURIComponent('id:' + productId)).then(function (r) {
            if (!r.ok || !Array.isArray(r.body)) return;
            var match = (r.body || []).filter(function (p) { return parseInt(p.id, 10) === parseInt(productId, 10); })[0];
            if (!match) return;
            var name = root.querySelector('[data-ocd-map-name="' + productId + '"]');
            var thumb = root.querySelector('[data-ocd-map-thumb="' + productId + '"]');
            if (name) name.textContent = match.name + ' (#' + match.id + ')';
            if (thumb && match.image) thumb.src = match.image;
        });
    }

    function getMapAndUpdate(root, mutator) {
        api('admin/product-map').then(function (r) {
            if (!r.ok) return alert((r.body && r.body.message) || 'Failed.');
            var map = r.body || {};
            var next = mutator(Object.assign({}, map));
            api('admin/product-map', { method: 'POST', body: { map: next } }).then(function (rr) {
                if (rr.ok) loadProductMap(root);
                else alert((rr.body && rr.body.message) || 'Failed.');
            });
        });
    }

    function searchExistingProducts(root, query) {
        var results = root.querySelector('[data-ocd-list="wc-products"]');
        if (!results) return;
        results.hidden = false;
        results.innerHTML = '<li class="ocd-empty">Searching…</li>';
        api('admin/wc-products?per_page=15&search=' + encodeURIComponent(query)).then(function (r) {
            if (!r.ok) { results.innerHTML = '<li class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</li>'; return; }
            var rows = Array.isArray(r.body) ? r.body : [];
            if (!rows.length) { results.innerHTML = '<li class="ocd-empty">No matching WooCommerce products. Make sure the product exists in WooCommerce — the dashboard never creates new products.</li>'; return; }
            results.innerHTML = rows.map(function (p) {
                return '<li class="ocd-product-result" data-ocd-pick-product="' + escapeHtml(p.id) + '">' +
                    (p.image ? '<img alt="" src="' + escapeHtml(p.image) + '" />' : '<span class="ocd-product-result__noimg"></span>') +
                    '<div>' +
                        '<strong>' + escapeHtml(p.name) + '</strong> ' +
                        '<span class="ocd-muted">#' + escapeHtml(p.id) + (p.sku ? ' · ' + escapeHtml(p.sku) : '') + '</span>' +
                        (p.price_html ? '<div class="ocd-muted">' + p.price_html + '</div>' : '') +
                    '</div>' +
                '</li>';
            }).join('');
            results.querySelectorAll('[data-ocd-pick-product]').forEach(function (li) {
                li.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    var pid = li.getAttribute('data-ocd-pick-product');
                    var name = li.querySelector('strong') ? li.querySelector('strong').textContent : '';
                    var slug = prompt('Feature slug for "' + name + '" (e.g. analytics-pro):');
                    if (!slug) return;
                    var label = prompt('Display label?', name) || name;
                    getMapAndUpdate(root, function (m) {
                        m[pid] = { slug: slug, label: label };
                        return m;
                    });
                    var input = root.querySelector('[data-ocd-search="wc-products"]');
                    if (input) input.value = '';
                    results.innerHTML = '';
                    results.hidden = true;
                });
            });
        });
    }

    function loadStoreCategory(root) {
        var block = root.querySelector('[data-ocd-block="store-category"]');
        if (!block) return;
        block.innerHTML = '<p class="ocd-empty">Loading…</p>';
        api('admin/store-category').then(function (r) {
            if (!r.ok) { block.innerHTML = '<p class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>'; return; }
            var data = r.body || {};
            var available = (data.available || []).slice();
            available.unshift({ slug: '', name: '— None —', count: 0 });
            var current = data.category || '';
            block.innerHTML = '<label>Category ' +
                '<select class="ocd-input" data-ocd-store-category>' +
                available.map(function (t) {
                    return '<option value="' + escapeHtml(t.slug) + '"' + (t.slug === current ? ' selected' : '') + '>' +
                        escapeHtml(t.name) + (t.count ? ' (' + t.count + ')' : '') +
                    '</option>';
                }).join('') +
                '</select></label> ' +
                '<button class="ocd-btn ocd-btn--primary" data-ocd-action="save-store-category">Save</button>';
            var btn = block.querySelector('[data-ocd-action="save-store-category"]');
            if (btn) btn.addEventListener('click', function () {
                var sel = block.querySelector('[data-ocd-store-category]');
                var value = sel ? sel.value : '';
                api('admin/store-category', { method: 'POST', body: { category: value } }).then(function (rr) {
                    if (rr.ok) loadStoreCategory(root);
                    else alert((rr.body && rr.body.message) || 'Failed.');
                });
            });
        });
    }

    function loadCustomers(root, search) {
        var body = root.querySelector('[data-ocd-list="admin-customers"]');
        if (!body) return;
        body.innerHTML = '<tr><td colspan="5" class="ocd-empty">Loading…</td></tr>';
        var q = search ? '?search=' + encodeURIComponent(search) : '';
        api('admin/customers' + q).then(function (r) {
            if (!r.ok) { body.innerHTML = '<tr><td colspan="5" class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Not configured.') + '</td></tr>'; return; }
            var rows = r.body || [];
            body.innerHTML = rows.length ? rows.map(function (c) {
                var name = (c.first_name || '') + ' ' + (c.last_name || '');
                return '<tr><td>#' + escapeHtml(c.id) + '</td><td>' + escapeHtml(name.trim() || c.username) + '</td><td>' + escapeHtml(c.email) + '</td><td>' + escapeHtml(c.orders_count || 0) + '</td><td>' + escapeHtml(fmtMoney(c.total_spent, 'USD')) + '</td></tr>';
            }).join('') : '<tr><td colspan="5" class="ocd-empty">No customers.</td></tr>';
        });
    }

    function loadSubscriptions(root) {
        var body = root.querySelector('[data-ocd-list="admin-subscriptions"]');
        var statusSel = root.querySelector('[data-ocd-filter="sub-status"]');
        if (!body) return;
        var status = statusSel ? statusSel.value : '';
        body.innerHTML = '<tr><td colspan="5" class="ocd-empty">Loading…</td></tr>';
        api('admin/subscriptions' + (status ? '?status=' + encodeURIComponent(status) : '')).then(function (r) {
            if (!r.ok) { body.innerHTML = '<tr><td colspan="5" class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Not configured.') + '</td></tr>'; return; }
            var rows = r.body || [];
            body.innerHTML = rows.length ? rows.map(function (s) {
                var customer = ((s.billing && (s.billing.first_name || s.billing.last_name)) ? (s.billing.first_name + ' ' + s.billing.last_name) : (s.billing && s.billing.email)) || ('#' + s.customer_id);
                var actions = '';
                if (s.status === 'on-hold' || s.status === 'pending') actions += '<button class="ocd-btn ocd-btn--ghost" data-ocd-sub-action="' + escapeHtml(s.id) + '" data-ocd-target-status="active">Activate</button> ';
                if (s.status === 'active') actions += '<button class="ocd-btn ocd-btn--ghost" data-ocd-sub-action="' + escapeHtml(s.id) + '" data-ocd-target-status="on-hold">Hold</button> ';
                if (s.status !== 'cancelled') actions += '<button class="ocd-btn ocd-btn--danger" data-ocd-sub-action="' + escapeHtml(s.id) + '" data-ocd-target-status="cancelled">Cancel</button>';
                return '<tr><td>#' + escapeHtml(s.id) + '</td><td>' + escapeHtml(customer) + '</td><td>' + badge(s.status) + '</td><td>' + escapeHtml(fmtMoney(s.total, s.currency)) + '</td><td>' + actions + '</td></tr>';
            }).join('') : '<tr><td colspan="5" class="ocd-empty">No subscriptions.</td></tr>';
            body.querySelectorAll('[data-ocd-sub-action]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var id = b.getAttribute('data-ocd-sub-action');
                    var target = b.getAttribute('data-ocd-target-status');
                    if (!confirm('Set subscription #' + id + ' to "' + target + '"?')) return;
                    b.disabled = true;
                    api('admin/subscriptions/' + id, { method: 'POST', body: { status: target } }).then(function (rr) {
                        if (rr.ok) loadSubscriptions(root);
                        else { b.disabled = false; alert((rr.body && rr.body.message) || 'Failed.'); }
                    });
                });
            });
        });
    }

    function loadContacts(root, query) {
        var list = root.querySelector('[data-ocd-list="admin-contacts"]');
        if (!list) return;
        list.innerHTML = '<li class="ocd-empty">Loading…</li>';
        var q = query ? '?query=' + encodeURIComponent(query) : '';
        api('admin/contacts' + q).then(function (r) {
            if (!r.ok) { list.innerHTML = '<li class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Not configured.') + '</li>'; return; }
            var contacts = (r.body && r.body.contacts) || [];
            list.innerHTML = contacts.length ? contacts.map(function (c) {
                var name = (c.firstName || '') + ' ' + (c.lastName || '');
                return '<li><span><strong>' + escapeHtml(name.trim() || c.contactName || c.email) + '</strong> <span class="ocd-muted">' + escapeHtml(c.email || '') + '</span></span><span class="ocd-muted">' + escapeHtml(c.phone || '') + '</span></li>';
            }).join('') : '<li class="ocd-empty">No contacts.</li>';
        });
    }

    function loadOpportunities(root) {
        var list = root.querySelector('[data-ocd-list="admin-opportunities"]');
        if (!list) return;
        list.innerHTML = '<li class="ocd-empty">Loading…</li>';
        api('admin/opportunities').then(function (r) {
            if (!r.ok) { list.innerHTML = '<li class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Not configured.') + '</li>'; return; }
            var opps = (r.body && r.body.opportunities) || (Array.isArray(r.body) ? r.body : []);
            list.innerHTML = opps.length ? opps.map(function (op) {
                return '<li><span><strong>' + escapeHtml(op.name || op.title || ('Opportunity ' + (op.id || ''))) + '</strong> ' + badge(op.status || 'open') + '</span><span class="ocd-muted">' + escapeHtml(fmtMoney(op.monetaryValue || op.amount, 'USD')) + '</span></li>';
            }).join('') : '<li class="ocd-empty">No opportunities.</li>';
        });
    }

    function loadSync(root) {
        var list = root.querySelector('[data-ocd-list="sync-status"]');
        if (!list) return;
        list.innerHTML = '<li class="ocd-empty">Pinging…</li>';
        api('admin/sync-status').then(function (r) {
            if (!r.ok) { list.innerHTML = '<li class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</li>'; return; }
            var d = r.body;
            function row(label, blob) {
                var dot = blob.ok ? '🟢' : (blob.configured ? '🔴' : '⚪');
                return '<li><strong>' + dot + ' ' + escapeHtml(label) + ':</strong> ' + (blob.configured ? (blob.ok ? 'Connected' : escapeHtml(blob.error || 'Error')) : 'Not configured') + '</li>';
            }
            list.innerHTML = row('HighLevel (agency)', d.highlevel) + row('WooCommerce', d.woocommerce) + '<li class="ocd-muted">Checked: ' + escapeHtml(d.last_check) + '</li>';
        });
    }

    document.querySelectorAll('.ocd-app[data-ocd-role="customer"]').forEach(initCustomer);
    document.querySelectorAll('.ocd-app[data-ocd-role="admin"]').forEach(initAdmin);
})();
