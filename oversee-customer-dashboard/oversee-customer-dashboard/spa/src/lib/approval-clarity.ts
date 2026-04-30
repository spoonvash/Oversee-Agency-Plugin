// approval-clarity.ts
// ─────────────────────────────────────────────────────────────────────────────
// Single source of truth for "who needs to do what" wording across the app.
//
// Deliberately a derivation layer rather than a schema change. The demo store's
// existing fields (BoardItem.status / .assignee / .due, FileEntry.approval /
// .uploadedBy / .boardName) already carry the data we need; this file maps them
// to the explicit approval-vocabulary the UX requires:
//
//   Owner          — person responsible for doing the work
//   Approver       — person/team responsible for the decision
//   Submitted by   — who pushed it for review
//   Waiting on     — who the next action is blocked on (party + name)
//   Due            — the deadline (if any)
//   Last decision  — who made the most recent approve/changes decision and when
//   Approval status — coarse state that drives color + label
//   Next action    — short verb-led sentence telling the viewer what to do next
//
// All consumers (client + admin pages, item drawer, file dialog) should call
// `getItemApproval()` or `getFileApproval()` and never invent their own copy.
// ─────────────────────────────────────────────────────────────────────────────
import type { BoardItem, FileEntry, DiscussionPost } from "@/lib/demo-store";

// Coarse status drives color + tone everywhere. Mapped to existing item/file
// state so introducing this layer doesn't touch the store schema.
export type ApprovalStatus =
  | "needs_client_approval"
  | "needs_staff_review"
  | "waiting_on_oversee"
  | "changes_requested"
  | "approved"
  | "not_required";

export type WaitingParty = "client" | "oversee" | "staff" | "none";

export type Role = "client" | "admin";

export type ApprovalMeta = {
  status: ApprovalStatus;
  // Short sentence-case label shown in pills / banners.
  statusLabel: string;
  // Tone hook for shared StatusPill (semantic — never raw color).
  tone: "primary" | "warning" | "info" | "success" | "danger" | "neutral";
  // Who is responsible for doing the work.
  ownerName: string;
  ownerRole: "Oversee staff" | "Client";
  // Who has to make the decision next.
  approverName: string;
  approverRole: "Client" | "Oversee staff" | "Oversee team";
  submittedBy?: string;
  waitingOn: WaitingParty;
  waitingOnName?: string;
  dueAt?: string;
  lastDecisionBy?: string;
  lastDecisionAt?: string;
  // What the viewer (client OR admin) should do next, in plain language.
  nextActionLabel: string;
  // Helper: should the role currently viewing be CTA-active?
  ctaForRole: (r: Role) => "approve" | "request_changes" | "none";
};

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

export const STAFF_NAMES = new Set([
  "Sasha Patel",
  "Devon Ortiz",
  "Lena Howe",
  "Marcus Reed",
  "Priya Shah",
]);

export const CLIENT_PRIMARY_NAME = "Maya Lin";

export function isStaffName(name?: string) {
  if (!name) return false;
  return STAFF_NAMES.has(name);
}

// ─────────────────────────────────────────────────────────────────────────────
// Item — derives approval metadata from a BoardItem.
// ─────────────────────────────────────────────────────────────────────────────
export function getItemApproval(
  item: BoardItem,
  opts: { clientName?: string; staffReviewer?: string } = {},
): ApprovalMeta {
  const clientName = opts.clientName ?? CLIENT_PRIMARY_NAME;
  const staffReviewer = opts.staffReviewer ?? "Sasha Patel";

  const approvalDecision = lastApprovalDecision(item.discussion);
  // Identify "needs staff review" via tag or convention. Keep the rule small
  // and explicit so it can be tweaked in one place.
  const needsStaffReview =
    item.tags?.includes("staff-review") ||
    item.tags?.includes("internal-review") ||
    (item.workflowStage?.toLowerCase().includes("review") && item.status !== "review");

  const ownerName = item.assignee || staffReviewer;
  const ownerRole: ApprovalMeta["ownerRole"] = isStaffName(ownerName)
    ? "Oversee staff"
    : "Client";

  // Default decision → resolved by item state.
  if (item.status === "done") {
    return {
      status: "approved",
      statusLabel: "Approved",
      tone: "success",
      ownerName,
      ownerRole,
      approverName: approvalDecision?.authorName ?? clientName,
      approverRole: approvalDecision?.authorRole === "admin" ? "Oversee staff" : "Client",
      submittedBy: ownerName,
      waitingOn: "none",
      dueAt: item.due,
      lastDecisionBy: approvalDecision?.authorName ?? clientName,
      lastDecisionAt: approvalDecision?.postedAt ?? undefined,
      nextActionLabel: "Approved · nothing else to do",
      ctaForRole: () => "none",
    };
  }

  if (item.status === "review") {
    if (needsStaffReview) {
      const reviewer = staffReviewer;
      return {
        status: "needs_staff_review",
        statusLabel: "Needs staff review",
        tone: "warning",
        ownerName,
        ownerRole,
        approverName: reviewer,
        approverRole: "Oversee staff",
        submittedBy: ownerName,
        waitingOn: "staff",
        waitingOnName: reviewer,
        dueAt: item.due,
        lastDecisionBy: approvalDecision?.authorName,
        lastDecisionAt: approvalDecision?.postedAt,
        nextActionLabel: `Staff review by ${reviewer}`,
        ctaForRole: (r) => (r === "admin" ? "approve" : "none"),
      };
    }
    return {
      status: "needs_client_approval",
      statusLabel: "Needs client approval",
      tone: "primary",
      ownerName,
      ownerRole,
      approverName: clientName,
      approverRole: "Client",
      submittedBy: ownerName,
      waitingOn: "client",
      waitingOnName: clientName,
      dueAt: item.due,
      lastDecisionBy: approvalDecision?.authorName,
      lastDecisionAt: approvalDecision?.postedAt,
      nextActionLabel: `Waiting on ${clientName} to approve`,
      ctaForRole: (r) => (r === "client" ? "approve" : "none"),
    };
  }

  if (item.status === "client-input") {
    return {
      status: "needs_client_approval",
      statusLabel: "Waiting on client",
      tone: "primary",
      ownerName,
      ownerRole,
      approverName: clientName,
      approverRole: "Client",
      submittedBy: ownerName,
      waitingOn: "client",
      waitingOnName: clientName,
      dueAt: item.due,
      nextActionLabel: `Waiting on ${clientName} for input`,
      ctaForRole: () => "none",
    };
  }

  if (item.status === "stuck") {
    // Whoever last requested changes is the source of truth on who to talk to.
    if (approvalDecision?.body?.toLowerCase().includes("changes")) {
      return {
        status: "changes_requested",
        statusLabel: "Changes requested",
        tone: "danger",
        ownerName,
        ownerRole,
        approverName: clientName,
        approverRole: "Client",
        submittedBy: ownerName,
        waitingOn: "oversee",
        waitingOnName: ownerName,
        dueAt: item.due,
        lastDecisionBy: approvalDecision.authorName,
        lastDecisionAt: approvalDecision.postedAt,
        nextActionLabel: `Oversee revising — ${ownerName}`,
        ctaForRole: () => "none",
      };
    }
    return {
      status: "waiting_on_oversee",
      statusLabel: "Blocked",
      tone: "danger",
      ownerName,
      ownerRole,
      approverName: ownerName,
      approverRole: "Oversee staff",
      submittedBy: ownerName,
      waitingOn: "oversee",
      waitingOnName: ownerName,
      dueAt: item.due,
      nextActionLabel: `Blocked — ${ownerName} unblocking`,
      ctaForRole: () => "none",
    };
  }

  // not-started / in-progress
  return {
    status: "waiting_on_oversee",
    statusLabel: item.status === "in-progress" ? "Oversee working on it" : "Queued with Oversee",
    tone: "info",
    ownerName,
    ownerRole,
    approverName: ownerName,
    approverRole: "Oversee staff",
    submittedBy: ownerName,
    waitingOn: "oversee",
    waitingOnName: ownerName,
    dueAt: item.due,
    nextActionLabel: `${ownerName} working on it`,
    ctaForRole: () => "none",
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// File — derives approval metadata from a FileEntry.
// ─────────────────────────────────────────────────────────────────────────────
export function getFileApproval(
  file: FileEntry,
  opts: { clientName?: string } = {},
): ApprovalMeta {
  const clientName = opts.clientName ?? CLIENT_PRIMARY_NAME;
  const uploaderIsStaff = isStaffName(file.uploadedBy);
  const ownerName = file.uploadedBy;
  const ownerRole: ApprovalMeta["ownerRole"] = uploaderIsStaff ? "Oversee staff" : "Client";

  if (file.approval === "approved") {
    return {
      status: "approved",
      statusLabel: "Approved",
      tone: "success",
      ownerName,
      ownerRole,
      approverName: clientName,
      approverRole: "Client",
      submittedBy: file.uploadedBy,
      waitingOn: "none",
      lastDecisionBy: clientName,
      lastDecisionAt: file.uploadedAt,
      nextActionLabel: "Approved · nothing else to do",
      ctaForRole: () => "none",
    };
  }
  if (file.approval === "changes-requested") {
    return {
      status: "changes_requested",
      statusLabel: "Changes requested",
      tone: "danger",
      ownerName,
      ownerRole,
      approverName: clientName,
      approverRole: "Client",
      submittedBy: file.uploadedBy,
      waitingOn: "oversee",
      waitingOnName: ownerName,
      lastDecisionBy: clientName,
      lastDecisionAt: file.uploadedAt,
      nextActionLabel: `Oversee revising — ${ownerName}`,
      ctaForRole: () => "none",
    };
  }
  if (file.approval === "n/a") {
    return {
      status: "not_required",
      statusLabel: "Reference only",
      tone: "neutral",
      ownerName,
      ownerRole,
      approverName: "—",
      approverRole: ownerRole === "Client" ? "Oversee staff" : "Client",
      submittedBy: file.uploadedBy,
      waitingOn: "none",
      nextActionLabel: "Reference file · no decision needed",
      ctaForRole: () => "none",
    };
  }
  // pending — uploader determines who needs to approve.
  // Staff upload → client decides. Client upload → staff reviews.
  if (uploaderIsStaff) {
    return {
      status: "needs_client_approval",
      statusLabel: "Needs client approval",
      tone: "primary",
      ownerName,
      ownerRole: "Oversee staff",
      approverName: clientName,
      approverRole: "Client",
      submittedBy: file.uploadedBy,
      waitingOn: "client",
      waitingOnName: clientName,
      nextActionLabel: `Waiting on ${clientName} to approve`,
      ctaForRole: (r) => (r === "client" ? "approve" : "none"),
    };
  }
  return {
    status: "needs_staff_review",
    statusLabel: "Needs staff review",
    tone: "warning",
    ownerName,
    ownerRole: "Client",
    approverName: "Sasha Patel",
    approverRole: "Oversee staff",
    submittedBy: file.uploadedBy,
    waitingOn: "staff",
    waitingOnName: "Sasha Patel",
    nextActionLabel: "Staff review by Sasha Patel",
    ctaForRole: (r) => (r === "admin" ? "approve" : "none"),
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────
function lastApprovalDecision(discussion: DiscussionPost[]): DiscussionPost | undefined {
  // Walk newest → oldest; return the first system "approval" or human approval/changes post.
  const ordered = [...discussion].sort(
    (a, b) => new Date(b.postedAt).getTime() - new Date(a.postedAt).getTime(),
  );
  return ordered.find(
    (p) =>
      p.systemKind === "approval" ||
      /(approved|changes requested|requested changes)/i.test(p.body),
  );
}

// Role-aware copy. Both sides of the table use the same "who is waiting on
// whom" data; the wording flexes for the viewer.
export function waitingOnPhrase(meta: ApprovalMeta, viewer: Role): string {
  if (meta.waitingOn === "none") return "Nobody — done";
  if (viewer === "client") {
    if (meta.waitingOn === "client") return "Waiting on you";
    if (meta.waitingOn === "staff") return "Oversee is reviewing internally";
    return `Oversee is working on it${meta.waitingOnName ? ` — ${meta.waitingOnName}` : ""}`;
  }
  // admin viewer
  if (meta.waitingOn === "client") {
    return `Waiting on client: ${meta.waitingOnName ?? "—"}`;
  }
  if (meta.waitingOn === "staff") {
    return `Staff review: ${meta.waitingOnName ?? "—"}`;
  }
  return `Oversee work: ${meta.waitingOnName ?? "—"}`;
}

export function approverLine(meta: ApprovalMeta): string {
  if (meta.status === "approved" && meta.lastDecisionBy) {
    return `Approved by ${meta.lastDecisionBy}`;
  }
  if (meta.status === "changes_requested" && meta.lastDecisionBy) {
    return `Changes requested by ${meta.lastDecisionBy}`;
  }
  if (meta.approverName === "—") return "No approver required";
  return `Approver: ${meta.approverName} (${meta.approverRole})`;
}

// Simple "due Apr 16" / "Due in 2 days" / "2 days overdue" formatting.
export function formatDue(due?: string): string | undefined {
  if (!due) return undefined;
  const date = new Date(due);
  if (isNaN(date.getTime())) return undefined;
  const now = Date.now();
  const diffDays = Math.round((date.getTime() - now) / 86400000);
  const fmt = date.toLocaleDateString(undefined, { month: "short", day: "numeric" });
  if (diffDays < 0) return `${Math.abs(diffDays)}d overdue · ${fmt}`;
  if (diffDays === 0) return `Due today · ${fmt}`;
  if (diffDays === 1) return `Due tomorrow · ${fmt}`;
  if (diffDays < 14) return `Due in ${diffDays}d · ${fmt}`;
  return `Due ${fmt}`;
}
