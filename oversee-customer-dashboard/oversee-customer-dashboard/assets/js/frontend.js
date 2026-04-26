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
            return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; });
        });
    }

    function fmtMoney(amount, currency) {
        if (amount === undefined || amount === null || amount === '') return '—';
        var n = parseFloat(amount);
        if (isNaN(n)) return amount;
        try {
            return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(n);
        } catch (e) { return (currency || '$') + n.toFixed(2); }
    }

    function fmtDate(s) {
        if (!s) return '—';
        var d = new Date(s);
        if (isNaN(d.getTime())) return s;
        return d.toLocaleDateString();
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

    function initCustomer(root) {
        var subsBody = root.querySelector('[data-ocd-list="subscriptions"]');
        var ordersList = root.querySelector('[data-ocd-list="orders"]');
        var oppsList = root.querySelector('[data-ocd-list="opportunities"]');
        var crmBlock = root.querySelector('[data-ocd-block="crm-contact"]');
        var hint = root.querySelector('[data-ocd-hint="subs"]');

        api('customer/dashboard').then(function (res) {
            if (!res.ok) {
                if (subsBody) subsBody.innerHTML = '<tr><td colspan="5" class="ocd-empty">' + escapeHtml(res.body && res.body.message || 'Failed to load.') + '</td></tr>';
                return;
            }
            var data = res.body;
            var subs = data.subscriptions || [];
            var orders = data.orders || [];
            var crm = data.crm || null;

            var activeCount = subs.filter(function (s) { return s.status === 'active'; }).length;
            setText(root.querySelector('[data-ocd-stat="active-subs"]'), activeCount);
            setText(root.querySelector('[data-ocd-stat="total-orders"]'), orders.length);
            setText(root.querySelector('[data-ocd-stat="open-opps"]'), crm && crm.opportunities ? crm.opportunities.length : 0);

            // Subscriptions
            if (subsBody) {
                if (!subs.length) {
                    subsBody.innerHTML = '<tr><td colspan="5" class="ocd-empty">' + (data.connections.woocommerce ? 'No subscriptions found.' : 'WooCommerce not connected.') + '</td></tr>';
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
                if (hint) hint.textContent = subs.length + ' subscription' + (subs.length === 1 ? '' : 's');
            }

            // Orders
            if (ordersList) {
                if (!orders.length) {
                    ordersList.innerHTML = '<li class="ocd-empty">' + (data.connections.woocommerce ? 'No recent orders.' : 'WooCommerce not connected.') + '</li>';
                } else {
                    ordersList.innerHTML = orders.slice(0, 8).map(function (o) {
                        return '<li>' +
                            '<span><strong>#' + escapeHtml(o.number || o.id) + '</strong> — ' + escapeHtml(fmtDate(o.date_created_gmt || o.date_created)) + ' &middot; ' + badge(o.status) + '</span>' +
                            '<span class="ocd-muted">' + escapeHtml(fmtMoney(o.total, o.currency)) + '</span>' +
                        '</li>';
                    }).join('');
                }
            }

            // CRM contact
            if (crmBlock) {
                if (!data.connections.highlevel) {
                    crmBlock.innerHTML = '<p class="ocd-muted">HighLevel CRM not connected.</p>';
                } else if (!crm || !crm.contact) {
                    crmBlock.innerHTML = '<p class="ocd-muted">No matching CRM contact for your email.</p>';
                } else {
                    var c = crm.contact;
                    var name = (c.firstName || '') + ' ' + (c.lastName || '');
                    crmBlock.innerHTML =
                        '<p><strong>' + escapeHtml(name.trim() || c.contactName || c.email) + '</strong></p>' +
                        '<p class="ocd-muted">' + escapeHtml(c.email || '') + (c.phone ? ' &middot; ' + escapeHtml(c.phone) : '') + '</p>' +
                        (c.companyName ? '<p>' + escapeHtml(c.companyName) + '</p>' : '');
                }
            }

            // Opportunities
            if (oppsList) {
                var opps = (crm && crm.opportunities) || [];
                if (!data.connections.highlevel) {
                    oppsList.innerHTML = '<li class="ocd-empty">HighLevel CRM not connected.</li>';
                } else if (!opps.length) {
                    oppsList.innerHTML = '<li class="ocd-empty">No opportunities.</li>';
                } else {
                    oppsList.innerHTML = opps.map(function (op) {
                        return '<li>' +
                            '<span><strong>' + escapeHtml(op.name || op.title || ('Opportunity ' + (op.id || ''))) + '</strong>' +
                                ' &middot; ' + badge(op.status || 'open') + '</span>' +
                            '<span class="ocd-muted">' + escapeHtml(fmtMoney(op.monetaryValue || op.amount, 'USD')) + '</span>' +
                        '</li>';
                    }).join('');
                }
            }

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
    }

    function initAdmin(root) {
        // Tabs
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
                if (name === 'customers') loadCustomers();
                if (name === 'subscriptions') loadSubscriptions();
                if (name === 'crm') { loadContacts(); loadOpportunities(); }
                if (name === 'sync') loadSync();
            });
        });

        // Overview: pull subscriptions for stats
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

        // Customers
        function loadCustomers(search) {
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
        var custSearch = root.querySelector('[data-ocd-search="customers"]');
        if (custSearch) {
            var t;
            custSearch.addEventListener('input', function () {
                clearTimeout(t);
                t = setTimeout(function () { loadCustomers(custSearch.value); }, 300);
            });
        }

        // Subscriptions
        function loadSubscriptions() {
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
                        api('admin/subscriptions/' + id, { method: 'POST', body: { status: target } }).then(function (r) {
                            if (r.ok) loadSubscriptions();
                            else { b.disabled = false; alert((r.body && r.body.message) || 'Failed.'); }
                        });
                    });
                });
            });
        }
        var statusSel = root.querySelector('[data-ocd-filter="sub-status"]');
        if (statusSel) statusSel.addEventListener('change', loadSubscriptions);

        // Contacts
        function loadContacts(query) {
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
        var contactSearch = root.querySelector('[data-ocd-search="contacts"]');
        if (contactSearch) {
            var t2;
            contactSearch.addEventListener('input', function () {
                clearTimeout(t2);
                t2 = setTimeout(function () { loadContacts(contactSearch.value); }, 350);
            });
        }

        // Opportunities
        function loadOpportunities() {
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

        // Sync status
        function loadSync() {
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
                list.innerHTML = row('HighLevel', d.highlevel) + row('WooCommerce', d.woocommerce) + '<li class="ocd-muted">Checked: ' + escapeHtml(d.last_check) + '</li>';
            });
        }
        var refreshSync = root.querySelector('[data-ocd-action="refresh-sync"]');
        if (refreshSync) refreshSync.addEventListener('click', loadSync);

        loadOverview();
    }

    document.querySelectorAll('.ocd-app[data-ocd-role="customer"]').forEach(initCustomer);
    document.querySelectorAll('.ocd-app[data-ocd-role="admin"]').forEach(initAdmin);
})();
