/**
 * Oversee Customer Dashboard — frontend controller.
 * Talks only to the WordPress REST namespace `ocd/v1` (server-side API tokens).
 */
(function () {
    'use strict';

    var cfg = window.OCD_CONFIG || {};
    if (!cfg.restUrl) return;

    function api(path, opts) {
        opts = opts || {};
        return fetch(cfg.restUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, ''), {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': cfg.nonce || ''
            },
            body: opts.body ? JSON.stringify(opts.body) : undefined
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
                if (name === 'tasks') loadCustomerTasks(root);
                if (name === 'store') loadCustomerStore(root);
                if (name === 'integrations') loadCustomerCRM(root);
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
                return '<li class="ocd-milestone ocd-milestone--' + escapeHtml(m.status) + '">' +
                    '<span class="ocd-milestone__dot' + (done ? ' is-done' : '') + '"></span>' +
                    '<div class="ocd-milestone__body">' +
                        '<strong>' + escapeHtml(m.title) + '</strong>' +
                        (m.due_date ? ' <span class="ocd-muted">· due ' + escapeHtml(fmtDate(m.due_date)) + '</span>' : '') +
                        (m.note ? '<p class="ocd-muted">' + escapeHtml(m.note) + '</p>' : '') +
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

    /* ---------------- Customer ---------------- */

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

            // Render projects/tasks in their tabs eagerly so stats reflect data already.
            renderProjects(root.querySelector('[data-ocd-list="projects"]'), projects, false);
            renderCustomerTasks(root.querySelector('[data-ocd-list="tasks"]'), tasks);

            // Wire cancel actions
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

        // Messages form
        var sendForm = root.querySelector('[data-ocd-form="send-message"]');
        if (sendForm) {
            sendForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var ta = sendForm.querySelector('textarea[name="message"]');
                var btn = sendForm.querySelector('button[type="submit"]');
                if (!ta || !ta.value.trim()) return;
                btn.disabled = true;
                api('customer/messages', { method: 'POST', body: { message: ta.value } }).then(function (r) {
                    btn.disabled = false;
                    if (r.ok) {
                        ta.value = '';
                        loadCustomerMessages(root);
                    } else {
                        alert((r.body && r.body.message) || 'Failed to send.');
                    }
                });
            });
        }

        // CRM form (customer-owned)
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
                return '<div class="' + cls + '"><div class="ocd-msg__meta"><strong>' + label + '</strong> · ' + escapeHtml(fmtDateTime(m.created_at)) + '</div><div class="ocd-msg__body">' + escapeHtml(m.body).replace(/\n/g, '<br>') + '</div></div>';
            }).join('');
            thread.scrollTop = thread.scrollHeight;
        });
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

    function renderCustomerTasks(target, tasks) {
        if (!target) return;
        if (!tasks || !tasks.length) {
            target.innerHTML = '<li class="ocd-empty">No tasks assigned. You\'re all caught up!</li>';
            return;
        }
        target.innerHTML = tasks.map(function (t) {
            var done = t.status === 'completed';
            return '<li class="ocd-task' + (done ? ' is-done' : '') + '">' +
                '<label class="ocd-task__check">' +
                    '<input type="checkbox" data-ocd-task-toggle="' + escapeHtml(t.id) + '" ' + (done ? 'checked' : '') + '>' +
                    '<span></span>' +
                '</label>' +
                '<div class="ocd-task__body">' +
                    '<strong>' + escapeHtml(t.title) + '</strong>' +
                    (t.details ? '<p class="ocd-muted">' + escapeHtml(t.details) + '</p>' : '') +
                    (t.due_date ? '<p class="ocd-muted">Due ' + escapeHtml(fmtDate(t.due_date)) + '</p>' : '') +
                '</div>' +
                '<span class="ocd-task__status">' + badge(t.status) + '</span>' +
            '</li>';
        }).join('');

        target.querySelectorAll('[data-ocd-task-toggle]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var id = cb.getAttribute('data-ocd-task-toggle');
                var status = cb.checked ? 'completed' : 'open';
                cb.disabled = true;
                api('customer/tasks/' + id, { method: 'POST', body: { status: status } }).then(function (r) {
                    cb.disabled = false;
                    if (!r.ok) { cb.checked = !cb.checked; alert((r.body && r.body.message) || 'Failed.'); }
                    else loadCustomerTasks(cb.closest('.ocd-app'));
                });
            });
        });
    }

    function loadCustomerTasks(root) {
        var target = root.querySelector('[data-ocd-list="tasks"]');
        if (!target) return;
        target.innerHTML = '<li class="ocd-empty">Loading…</li>';
        api('customer/tasks').then(function (r) {
            if (!r.ok) { target.innerHTML = '<li class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</li>'; return; }
            renderCustomerTasks(target, r.body || []);
        });
    }

    function loadCustomerStore(root) {
        var target = root.querySelector('[data-ocd-list="store"]');
        if (!target) return;
        target.innerHTML = '<p class="ocd-empty">Loading…</p>';
        api('customer/store').then(function (r) {
            if (!r.ok) { target.innerHTML = '<p class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</p>'; return; }
            var listings = (r.body && r.body.listings) || [];
            if (!listings.length) {
                target.innerHTML = '<p class="ocd-muted">No add-ons configured yet. Oversee staff can add WooCommerce products to the dashboard store from Oversee Admin → Entitlements.</p>';
                return;
            }
            target.innerHTML = '<div class="ocd-store__grid">' + listings.map(function (it) {
                var owned = it.owned_status === 'active';
                var cta = owned
                    ? '<span class="ocd-pill ocd-pill--success">Active</span>'
                    : (it.available
                        ? '<a class="ocd-btn ocd-btn--primary" href="' + escapeHtml(it.add_to_cart) + '">Add to cart</a>'
                        : '<span class="ocd-muted">Unavailable</span>');
                return '<div class="ocd-store__card">' +
                    '<h3>' + escapeHtml(it.name || it.label) + '</h3>' +
                    '<p class="ocd-muted">' + escapeHtml(it.description || '') + '</p>' +
                    (it.price_html ? '<div class="ocd-store__price">' + it.price_html + '</div>' : '') +
                    '<div class="ocd-actions">' + cta +
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
                block.innerHTML = '<p class="ocd-muted">No customer-owned CRM connected. Use the form below to link your own account — separate from Oversee.</p>';
                if (form) form.hidden = false;
            }
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
                if (name === 'inbox') loadAdminInbox(root);
                if (name === 'projects') loadAdminProjects(root);
                if (name === 'tasks') loadAdminTasks(root);
                if (name === 'entitlements') loadProductMap(root);
                if (name === 'customers') loadCustomers(root);
                if (name === 'subscriptions') loadSubscriptions(root);
                if (name === 'crm') { loadContacts(root); loadOpportunities(root); }
                if (name === 'sync') loadSync(root);
            });
        });

        // Overview
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
            api('admin/tasks', { method: 'POST', body: { user_id: parseInt(userId, 10), title: title, due_date: due } }).then(function (r) {
                if (r.ok) loadAdminTasks(root);
                else alert((r.body && r.body.message) || 'Failed.');
            });
        });

        var addMap = root.querySelector('[data-ocd-action="add-product-map"]');
        if (addMap) addMap.addEventListener('click', function () {
            var pid = prompt('WooCommerce product ID?'); if (!pid) return;
            var slug = prompt('Feature slug (e.g. analytics-pro)?'); if (!slug) return;
            var label = prompt('Label?', slug);
            getMapAndUpdate(root, function (map) {
                map[pid] = { slug: slug, label: label || slug };
                return map;
            });
        });

        // Customers tab
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
                    return '<div class="' + cls + '"><div class="ocd-msg__meta"><strong>' + escapeHtml(label) + '</strong> · ' + escapeHtml(fmtDateTime(m.created_at)) + (m.hl_synced ? ' · <span class="ocd-pill ocd-pill--success">CRM ✓</span>' : '') + '</div><div class="ocd-msg__body">' + escapeHtml(m.body).replace(/\n/g, '<br>') + '</div></div>';
                }).join('');
                msgs.scrollTop = msgs.scrollHeight;
            }
            if (form) {
                form.hidden = false;
                form.onsubmit = function (e) {
                    e.preventDefault();
                    var ta = form.querySelector('textarea[name="message"]');
                    var btn = form.querySelector('button[type="submit"]');
                    if (!ta || !ta.value.trim()) return;
                    btn.disabled = true;
                    api('admin/messages/thread/' + userId, { method: 'POST', body: { message: ta.value } }).then(function (rr) {
                        btn.disabled = false;
                        if (rr.ok) { ta.value = ''; openThread(root, userId); }
                        else alert((rr.body && rr.body.message) || 'Failed.');
                    });
                };
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
        var body = root.querySelector('[data-ocd-list="admin-tasks"]');
        if (!body) return;
        body.innerHTML = '<tr><td colspan="6" class="ocd-empty">Loading…</td></tr>';
        api('admin/tasks').then(function (r) {
            if (!r.ok) { body.innerHTML = '<tr><td colspan="6" class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</td></tr>'; return; }
            var rows = r.body || [];
            body.innerHTML = rows.length ? rows.map(function (t) {
                return '<tr>' +
                    '<td>#' + escapeHtml(t.id) + '</td>' +
                    '<td>' + escapeHtml(t.user_id) + '</td>' +
                    '<td>' + escapeHtml(t.title) + '</td>' +
                    '<td>' + badge(t.status) + '</td>' +
                    '<td>' + escapeHtml(fmtDate(t.due_date)) + '</td>' +
                    '<td>' +
                        '<button class="ocd-btn ocd-btn--ghost" data-ocd-task-status="' + escapeHtml(t.id) + '">Set status</button> ' +
                        '<button class="ocd-btn ocd-btn--danger" data-ocd-task-del="' + escapeHtml(t.id) + '">Delete</button>' +
                    '</td>' +
                '</tr>';
            }).join('') : '<tr><td colspan="6" class="ocd-empty">No tasks yet.</td></tr>';
            body.querySelectorAll('[data-ocd-task-status]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var status = prompt('open / in-progress / completed / blocked / cancelled?');
                    if (!status) return;
                    api('admin/tasks/' + b.getAttribute('data-ocd-task-status'), { method: 'POST', body: { status: status } }).then(function (rr) {
                        if (rr.ok) loadAdminTasks(root);
                        else alert((rr.body && rr.body.message) || 'Failed.');
                    });
                });
            });
            body.querySelectorAll('[data-ocd-task-del]').forEach(function (b) {
                b.addEventListener('click', function () {
                    if (!confirm('Delete this task?')) return;
                    api('admin/tasks/' + b.getAttribute('data-ocd-task-del'), { method: 'DELETE' }).then(function () { loadAdminTasks(root); });
                });
            });
        });
    }

    function loadProductMap(root) {
        var body = root.querySelector('[data-ocd-list="admin-product-map"]');
        if (!body) return;
        body.innerHTML = '<tr><td colspan="4" class="ocd-empty">Loading…</td></tr>';
        api('admin/product-map').then(function (r) {
            if (!r.ok) { body.innerHTML = '<tr><td colspan="4" class="ocd-empty">' + escapeHtml((r.body && r.body.message) || 'Failed.') + '</td></tr>'; return; }
            var map = r.body || {};
            var keys = Object.keys(map);
            if (!keys.length) { body.innerHTML = '<tr><td colspan="4" class="ocd-empty">No mappings yet. Click "Add mapping" to get started.</td></tr>'; return; }
            body.innerHTML = keys.map(function (k) {
                var entry = map[k] || {};
                return '<tr><td>' + escapeHtml(k) + '</td><td><code>' + escapeHtml(entry.slug || '') + '</code></td><td>' + escapeHtml(entry.label || '') + '</td><td><button class="ocd-btn ocd-btn--danger" data-ocd-map-del="' + escapeHtml(k) + '">Remove</button></td></tr>';
            }).join('');
            body.querySelectorAll('[data-ocd-map-del]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var pid = b.getAttribute('data-ocd-map-del');
                    if (!confirm('Remove mapping for product #' + pid + '?')) return;
                    getMapAndUpdate(root, function (m) { delete m[pid]; return m; });
                });
            });
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
