// Helpers used by preview pages to give every interactive button visible
// behavior (toast / dialog) instead of going dead silently.
//
// Production action descriptions live in PROD_ACTIONS so they can also be
// surfaced in the QA / audit summary modal.

export type PreviewAction = {
  id: string;
  label: string;
  description: string;
  area: "client" | "admin";
  page: string;
};

export const PROD_ACTIONS: PreviewAction[] = [
  // ---------- Client ----------
  { id: "client.home.tile", label: "Home action tile", area: "client", page: "Home", description: "Navigates to the matching workspace tab (Tasks, Files, Forms, Contracts, Billing)." },
  { id: "client.tasks.move", label: "Move task between stages", area: "client", page: "Tasks", description: "Updates the WordPress + project workspace task and notifies the team via Pusher." },
  { id: "client.tasks.comment", label: "Post comment on task", area: "client", page: "Tasks", description: "Persists in the WP database, fans out via Pusher, and emails @-mentions via Resend." },
  { id: "client.tasks.attach.image", label: "Attach image to comment", area: "client", page: "Tasks", description: "Uploads to Bunny.net, attaches to the comment thread; client + admin both see thumbnails inline." },
  { id: "client.approval.respond", label: "Respond to approval (with image)", area: "client", page: "Tasks", description: "Approves or requests changes; optionally attaches image references; updates Monday-style status and notifies admin." },
  { id: "client.files.upload", label: "Upload file", area: "client", page: "Files", description: "Posts to WordPress + Bunny.net storage; runs virus scan; attaches to the active project." },
  { id: "client.files.download", label: "Download file", area: "client", page: "Files", description: "Streams the file from Bunny.net via a signed URL." },
  { id: "client.forms.save", label: "Save draft", area: "client", page: "Forms", description: "PATCH /wp-json/oversee/v1/forms/:id/draft \u2014 stored against the user account." },
  { id: "client.forms.submit", label: "Submit form", area: "client", page: "Forms", description: "POST submission; triggers project automations (template spawn, intake assignment)." },
  { id: "client.contracts.sign", label: "Sign contract", area: "client", page: "Contracts", description: "Hands off to HighLevel Documents for legally-binding signature; webhook returns signed PDF." },
  { id: "client.shop.add", label: "Add to cart", area: "client", page: "Browse Services", description: "Posts to /wp-json/wc/store/v1/cart preserving variant ID and selected attributes." },
  { id: "client.shop.checkout", label: "Checkout", area: "client", page: "Browse Services", description: "POST /wp-json/wc/store/v1/checkout with the cart token; Stripe Elements collects payment." },
  { id: "client.subs.pause", label: "Pause subscription", area: "client", page: "Subscriptions", description: "Patches Woo Subscriptions API and Stripe; stops next-period charge." },
  { id: "client.subs.cancel", label: "Cancel subscription", area: "client", page: "Subscriptions", description: "Schedules cancellation at period end via Woo Subscriptions." },
  { id: "client.billing.pay", label: "Pay invoice", area: "client", page: "Billing", description: "Charges the default Stripe payment method, marks invoice paid, emails receipt via Resend." },
  { id: "client.billing.method.add", label: "Add payment method", area: "client", page: "Billing", description: "Stripe SetupIntent flow via Stripe Elements." },
  { id: "client.messages.send", label: "Send message", area: "client", page: "Messages", description: "Posts to HighLevel Conversations; Pusher real-time updates the inbox for both sides." },
  { id: "client.messages.attach.image", label: "Send image in chat", area: "client", page: "Messages", description: "Posts a HighLevel media message; thumbnail rendered for both client and admin in real time." },
  { id: "client.schedule.book", label: "Book a meeting", area: "client", page: "Schedule", description: "Creates a HighLevel calendar event; Google Meet link generated; ICS emailed via Resend." },
  { id: "client.reports.download", label: "Download performance report", area: "client", page: "Performance Reports", description: "Renders the Looker Studio dashboard server-side to a branded PDF and streams it back." },
  { id: "client.reviews.add", label: "Add Reviews subscription", area: "client", page: "Reviews", description: "Posts the upsell to Woo, creates a $99/mo subscription, enables HighLevel reputation pipelines." },

  // ---------- Admin ----------
  { id: "admin.today.queue.open", label: "Open queue item", area: "admin", page: "Today", description: "Routes to the relevant workspace (Orders, Work, Payments) with the item highlighted." },
  { id: "admin.clients.new", label: "New client", area: "admin", page: "Clients", description: "Creates a WordPress user, sets the role to client, kicks off onboarding automation." },
  { id: "admin.clients.message", label: "Message client", area: "admin", page: "Clients", description: "Opens the unified Messages inbox with the client thread focused." },
  { id: "admin.clients.email", label: "Email client", area: "admin", page: "Clients", description: "Opens the email composer (Resend) with template pre-loaded." },
  { id: "admin.work.new.project", label: "New project", area: "admin", page: "Work", description: "Spawns a project workspace from a chosen Service Template, attaches client + PM." },
  { id: "admin.work.open", label: "Open project workspace", area: "admin", page: "Work", description: "Loads Monday-style board / timeline / files view scoped to that project." },
  { id: "admin.work.task.create", label: "Create task", area: "admin", page: "Work", description: "Adds a task to the chosen project with assignee, due date, Monday-style status, and visibility flag." },
  { id: "admin.work.task.share", label: "Share task with client", area: "admin", page: "Work", description: "Toggles the client_visible flag; client sees and can comment in their dashboard." },
  { id: "admin.work.status.move", label: "Move task status", area: "admin", page: "Work", description: "Updates Monday-style column (Backlog, Working On It, Waiting on Client, In Review, Approved, Stuck); fans out via Pusher." },
  { id: "admin.inbox.send", label: "Send reply", area: "admin", page: "Inbox", description: "Posts the reply to HighLevel Conversations on the matching channel (SMS, email, IG, FB)." },
  { id: "admin.inbox.attach.image", label: "Send image in conversation", area: "admin", page: "Inbox", description: "Uploads media to HighLevel; both sides see the image thumbnail in real time." },
  { id: "admin.inbox.ai", label: "AI draft reply", area: "admin", page: "Inbox", description: "Calls the Oversee LLM endpoint to draft 3 reply options based on conversation context." },
  { id: "admin.inbox.note", label: "Add internal note", area: "admin", page: "Inbox", description: "Internal-only note attached to the HighLevel thread; never sent to the customer." },
  { id: "admin.inbox.assign", label: "Assign conversation", area: "admin", page: "Inbox", description: "Assigns the HighLevel conversation to a teammate; updates triage queue." },
  { id: "admin.inbox.open.hl", label: "Open in HighLevel CRM", area: "admin", page: "Inbox", description: "Opens the matching HighLevel conversation in a new tab for full CRM context." },
  { id: "admin.orders.spawn", label: "Manually spawn project", area: "admin", page: "Orders", description: "Triggers the WooCommerce \u2192 Project automation manually using the order's mapped template." },
  { id: "admin.templates.create", label: "Create service template", area: "admin", page: "Service Templates", description: "Opens the workflow builder; saves to the templates table; selectable from Catalog mappings." },
  { id: "admin.templates.test", label: "Test spawn", area: "admin", page: "Service Templates", description: "Runs the template against a test client to preview the spawned workspace structure." },
  { id: "admin.catalog.edit", label: "Edit catalog item", area: "admin", page: "Service Catalog", description: "Round-trips to WooCommerce to update name, price, attributes, and template mapping." },
  { id: "admin.payments.refund", label: "Refund payment", area: "admin", page: "Payments", description: "Calls Stripe refunds API; marks invoice / order; notifies the customer." },
  { id: "admin.forms.new", label: "New form", area: "admin", page: "Forms", description: "Opens the form builder; saved to the forms table; can be assigned to a service template." },
  { id: "admin.forms.send", label: "Send form to client", area: "admin", page: "Forms", description: "Creates a personalized form record, emails the client a deep link." },
  { id: "admin.contracts.send", label: "Send contract", area: "admin", page: "Contracts", description: "Drafts via HighLevel Documents and emails the e-signature link to the client." },
  { id: "admin.approvals.review", label: "Review approval", area: "admin", page: "Files & Approvals", description: "Opens the approval card for review; admin sees client decision and any attached image references." },
  { id: "admin.approvals.send", label: "Send for client approval", area: "admin", page: "Files & Approvals", description: "Moves the item into the client\u2019s approval queue (status: In Review) and notifies via HighLevel." },
  { id: "admin.files.bulk", label: "Bulk file actions", area: "admin", page: "Files & Approvals", description: "Bulk approve / reject / archive across selected files; scoped to the current project." },
  { id: "admin.team.invite", label: "Invite teammate", area: "admin", page: "Team", description: "Creates a pending WP user, emails an invite link with role pre-set." },
  { id: "admin.settings.test", label: "Test integration connection", area: "admin", page: "Settings", description: "Pings the integration\u2019s health endpoint (HighLevel, Pusher, Bunny.net, Resend)." },
  { id: "admin.settings.hl.manage", label: "Manage in HighLevel", area: "admin", page: "Settings", description: "Deep-links into the HighLevel sub-account where conversations, workflows, and calendars are configured." },
];

export function actionsFor(area: "client" | "admin"): PreviewAction[] {
  return PROD_ACTIONS.filter((a) => a.area === area);
}
