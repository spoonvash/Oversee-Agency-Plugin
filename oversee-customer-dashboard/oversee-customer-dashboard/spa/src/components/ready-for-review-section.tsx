// Reusable "Recent previews" / "Ready for review" card grid.
// Renders pending FileEntry items as visually rich preview cards with thumbnail,
// status, board context, and a primary "Review & approve" CTA. Click anywhere on
// the card opens the PreviewApprovalDialog. Used on Client Home, Client My Work,
// and Admin Today.
import { useState } from "react";
import { ArrowRight, CheckCircle2, ImageIcon, RefreshCcw, ThumbsUp } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useDemoStore, type FileEntry } from "@/lib/demo-store";
import { PreviewApprovalDialog } from "@/components/preview-approval-dialog";
import { DocumentMockPage } from "@/components/document-preview";
import { getFileApproval } from "@/lib/approval-clarity";

type Props = {
  role: "client" | "admin";
  /** Optional title override */
  title?: string;
  /** Optional subtitle / helper line */
  subtitle?: string;
  /** Filter: show only files with these approval states. Defaults to all states. */
  approvalStates?: FileEntry["approval"][];
  /** Limit cards. Defaults to 6. */
  limit?: number;
  /** Layout density */
  variant?: "grid" | "row";
  /** Compact mode for sidebars */
  compact?: boolean;
};

const APPROVAL_TONE: Record<FileEntry["approval"], string> = {
  pending: "border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-900/60 dark:bg-orange-950/40 dark:text-orange-200",
  approved: "border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200",
 "changes-requested": "border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200",
 "n/a": "border-strong bg-muted text-zinc-700   dark:text-zinc-300",
};

// Visible label depends on who needs to act AND who is viewing.
// We compute it dynamically using getFileApproval() per card so a client
// sees "Waiting on you" while admins see "Waiting on client" for the same file.
function labelForFile(f: FileEntry, viewer: "client" | "admin"): string {
  const meta = getFileApproval(f);
  if (meta.status === "approved") return "Approved";
  if (meta.status === "changes_requested") return "Changes requested";
  if (meta.status === "not_required") return "Reference";
  if (meta.status === "needs_client_approval") {
    return viewer === "client" ? "Waiting on you" : "Waiting on client";
  }
  if (meta.status === "needs_staff_review") {
    return viewer === "admin" ? "Waiting on Oversee staff" : "With Oversee staff";
  }
  return "Awaiting review";
}

const APPROVAL_ICON: Record<FileEntry["approval"], React.ComponentType<{ className?: string }>> = {
  pending: ThumbsUp,
  approved: CheckCircle2,
 "changes-requested": RefreshCcw,
 "n/a": ImageIcon,
};

export function ReadyForReviewSection({
  role,
  title,
  subtitle,
  approvalStates = ["pending", "approved", "changes-requested"],
  limit = 6,
  variant = "grid",
  compact = false,
}: Props) {
  const { files } = useDemoStore();
  const [openFile, setOpenFile] = useState<FileEntry | null>(null);

  // Sort: pending first, then most recent.
  const previews = files
    .filter((f) => approvalStates.includes(f.approval))
    .filter((f) => f.kind === "image" || f.kind === "pdf" || f.kind === "video")
    .sort((a, b) => {
      const aw = a.approval === "pending" ? 0 : a.approval === "changes-requested" ? 1 : 2;
      const bw = b.approval === "pending" ? 0 : b.approval === "changes-requested" ? 1 : 2;
      if (aw !== bw) return aw - bw;
      return new Date(b.uploadedAt).getTime() - new Date(a.uploadedAt).getTime();
    })
    .slice(0, limit);

  const pendingCount = previews.filter((f) => f.approval === "pending").length;

  if (previews.length === 0) {
    return (
      <section className="rounded-2xl border border-dashed border-border bg-card p-8 text-center" data-testid="recent-previews-empty">
        <div className="mx-auto grid size-12 place-items-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
          <CheckCircle2 className="size-6" />
        </div>
        <h3 className="mt-3 text-sm font-semibold">No previews waiting on review</h3>
        <p className="mt-1 text-xs text-muted-foreground">New deliverables will appear here.</p>
      </section>
    );
  }

  return (
    <section data-testid="recent-previews">
      <div className="mb-3 flex items-baseline justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold tracking-tight" data-testid="recent-previews-title">
            {title || (role === "client" ? "Recent previews" : "Recent client previews")}
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {subtitle ||
              (pendingCount > 0
                ? `${pendingCount} preview${pendingCount === 1 ? "" : "s"} need ${role === "client" ? "your" : "client"} attention`
                : "Latest deliverables and decisions")}
          </p>
        </div>
        <Badge variant="outline" className="tabular-nums" data-testid="recent-previews-count">
          {previews.length} shown
        </Badge>
      </div>

      <div
        className={
          variant === "row"
            ? "flex snap-x gap-3 overflow-x-auto pb-2"
            : compact
            ? "grid grid-cols-1 gap-3 sm:grid-cols-2"
            : "grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
        }
      >
        {previews.map((f) => {
          const Icon = APPROVAL_ICON[f.approval];
          const ctaLabel =
            f.approval === "pending"
              ? role === "client"
                ? "Review & approve"
                : "Open preview"
              : "View";
          return (
            <button
              key={f.id}
              type="button"
              onClick={() => setOpenFile(f)}
              className={`group relative flex flex-col overflow-hidden rounded-xl border bg-card text-left shadow-sm transition hover:shadow-md ${
                f.approval === "pending"
                  ? "border-orange-300 hover:border-orange-400 dark:border-orange-900/60 dark:hover:border-orange-700"
                  : "border-border hover:border-strong "
              } ${variant === "row" ? "min-w-[260px] snap-start" : ""}`}
              data-testid={`preview-card-${f.id}`}
              aria-label={`Open preview ${f.name}`}
            >
              {/* Thumbnail */}
              <div className="relative aspect-[4/3] w-full overflow-hidden bg-muted">
                {f.kind === "image" && f.src ? (
                  <img
                    src={f.src}
                    alt={f.name}
                    className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                  />
                ) : (
                  <DocumentMockPage file={f} />
                )}
                <div className="absolute left-2 top-2 flex items-center gap-1.5">
                  <Badge
                    variant="outline"
                    className={`gap-1 text-[10px] uppercase tracking-wide backdrop-blur ${APPROVAL_TONE[f.approval]}`}
                    data-testid={`preview-status-${f.id}`}
                  >
                    <Icon className="size-3" />
                    {labelForFile(f, role)}
                  </Badge>
                </div>
              </div>

              {/* Body */}
              <div className="flex flex-1 flex-col gap-1.5 px-4 py-3">
                <p className="truncate text-sm font-semibold leading-snug" data-testid={`preview-card-name-${f.id}`}>
                  {f.name}
                </p>
                <p className="truncate text-[11px] text-muted-foreground">
                  {f.boardName || "—"} · v{f.version}
                </p>
                <p className="truncate text-[11px] text-muted-foreground">
                  Uploaded {new Date(f.uploadedAt).toLocaleDateString(undefined, { month: "short", day: "numeric" })} by {f.uploadedBy}
                </p>
              </div>

              {/* Footer CTA */}
              <div
                className={`flex items-center justify-between gap-2 border-t px-4 py-2.5 text-xs font-semibold transition ${
                  f.approval === "pending"
                    ? "border-orange-200 bg-orange-50/60 text-orange-800 group-hover:bg-orange-100 dark:border-orange-900/60 dark:bg-orange-950/30 dark:text-orange-200 dark:group-hover:bg-orange-950/50"
                    : "border-border bg-muted text-foreground group-hover:bg-muted/50 dark:group-hover:bg-zinc-900"
                }`}
              >
                <span>{ctaLabel}</span>
                <ArrowRight className="size-3.5 transition group-hover:translate-x-0.5" />
              </div>
            </button>
          );
        })}
      </div>

      <PreviewApprovalDialog file={openFile} onClose={() => setOpenFile(null)} role={role} />
    </section>
  );
}

/** Inline button for sidebar / dense areas. */
export function ReadyForReviewInlineList({
  role,
  limit = 4,
}: {
  role: "client" | "admin";
  limit?: number;
}) {
  const { files } = useDemoStore();
  const [openFile, setOpenFile] = useState<FileEntry | null>(null);

  const previews = files
    .filter((f) => f.approval === "pending")
    .sort((a, b) => new Date(b.uploadedAt).getTime() - new Date(a.uploadedAt).getTime())
    .slice(0, limit);

  if (previews.length === 0) {
    return (
      <p className="px-4 py-6 text-center text-xs text-muted-foreground">No previews waiting on review.</p>
    );
  }

  return (
    <>
      <ul className="divide-y divide-border" data-testid="recent-previews-inline">
        {previews.map((f) => (
          <li key={f.id}>
            <button
              type="button"
              onClick={() => setOpenFile(f)}
              className="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-orange-50/40 dark:hover:bg-orange-950/10"
              data-testid={`preview-inline-${f.id}`}
            >
              <div className="size-10 shrink-0 overflow-hidden rounded-md border border-border bg-muted">
                {f.kind === "image" && f.src ? (
                  <img src={f.src} alt={f.name} className="size-full object-cover" />
                ) : (
                  <DocumentMockPage file={f} compact />
                )}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{f.name}</p>
                <p className="truncate text-[11px] text-muted-foreground">{f.boardName}</p>
              </div>
              <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" />
            </button>
          </li>
        ))}
      </ul>
      <PreviewApprovalDialog file={openFile} onClose={() => setOpenFile(null)} role={role} />
    </>
  );
}
