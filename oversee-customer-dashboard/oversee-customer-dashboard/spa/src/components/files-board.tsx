// FilesBoard — shared files/approvals view used by client (read+approve) and admin (full).
// Cards show preview + Draft / For Review / Changes Requested / Approved status.
// Click opens lightbox with annotation pins (mock) + approve/request changes buttons.
import { useMemo, useState } from "react";
import {
  FileText,
  Image as ImageIcon,
} from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useDemoStore, type FileEntry } from "@/lib/demo-store";
import { shortDate } from "@/lib/format";
import { PreviewApprovalDialog } from "@/components/preview-approval-dialog";
import { getFileApproval } from "@/lib/approval-clarity";

type StatusKey = "all" | "draft" | "needs-client" | "needs-staff" | "changes" | "approved";

// Approval status visuals are derived from approval-clarity meta now so
// terminology stays in lock-step with the rest of the app.
const META_BADGE: Record<string, string> = {
  needs_client_approval:
    "border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300",
  needs_staff_review:
    "border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300",
  waiting_on_oversee:
    "border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300",
  changes_requested:
    "border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300",
  approved:
    "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300",
  not_required: "border-border bg-zinc-100 text-zinc-700",
};

export function FilesBoard({ role }: { role: "client" | "admin" }) {
  const { files } = useDemoStore();
  const [tab, setTab] = useState<StatusKey>("all");
  const [open, setOpen] = useState<FileEntry | null>(null);

  // Pre-derive approval meta so we can both filter and label by it.
  const filesWithMeta = useMemo(
    () => files.map((f) => ({ file: f, meta: getFileApproval(f) })),
    [files],
  );

  const visible = useMemo(() => {
    if (tab === "all") return filesWithMeta;
    if (tab === "draft") return filesWithMeta.filter((x) => x.file.approval === "n/a");
    if (tab === "needs-client")
      return filesWithMeta.filter((x) => x.meta.status === "needs_client_approval");
    if (tab === "needs-staff")
      return filesWithMeta.filter((x) => x.meta.status === "needs_staff_review");
    if (tab === "changes")
      return filesWithMeta.filter((x) => x.file.approval === "changes-requested");
    return filesWithMeta.filter((x) => x.file.approval === "approved");
  }, [filesWithMeta, tab]);

  const counts = {
    all: filesWithMeta.length,
    draft: filesWithMeta.filter((x) => x.file.approval === "n/a").length,
    needsClient: filesWithMeta.filter((x) => x.meta.status === "needs_client_approval").length,
    needsStaff: filesWithMeta.filter((x) => x.meta.status === "needs_staff_review").length,
    changes: filesWithMeta.filter((x) => x.file.approval === "changes-requested").length,
    approved: filesWithMeta.filter((x) => x.file.approval === "approved").length,
  };

  return (
    <div className="space-y-4" data-testid="files-board">
      <div>
        <h1 className="text-xl font-semibold tracking-tight md:text-2xl">
          {role === "admin" ? "Files & Approvals" : "Files"}
        </h1>
        <p className="mt-0.5 text-sm text-muted-foreground">
          {role === "admin"
            ? "Every deliverable in one place. Tabs split by who decides next — client vs Oversee staff."
            : "Click any file to preview, leave annotations, and approve or request changes."}
        </p>
      </div>

      <Tabs value={tab} onValueChange={(v) => setTab(v as StatusKey)}>
        <TabsList className="h-9 flex-wrap p-1">
          <TabsTrigger value="all" data-testid="files-tab-all" className="gap-1.5 text-xs">All <span className="ml-1 rounded bg-muted px-1.5 text-[10px]">{counts.all}</span></TabsTrigger>
          <TabsTrigger value="draft" data-testid="files-tab-draft" className="gap-1.5 text-xs">Drafts <span className="ml-1 rounded bg-muted px-1.5 text-[10px]">{counts.draft}</span></TabsTrigger>
          <TabsTrigger value="needs-client" data-testid="files-tab-review" className="gap-1.5 text-xs">
            Waiting on client
            <span className="ml-1 rounded bg-orange-100 px-1.5 text-[10px] text-orange-800 dark:bg-orange-950/60 dark:text-orange-300">
              {counts.needsClient}
            </span>
          </TabsTrigger>
          <TabsTrigger value="needs-staff" data-testid="files-tab-staff" className="gap-1.5 text-xs">
            Staff review
            <span className="ml-1 rounded bg-amber-100 px-1.5 text-[10px] text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
              {counts.needsStaff}
            </span>
          </TabsTrigger>
          <TabsTrigger value="changes" data-testid="files-tab-changes" className="gap-1.5 text-xs">Changes requested <span className="ml-1 rounded bg-muted px-1.5 text-[10px]">{counts.changes}</span></TabsTrigger>
          <TabsTrigger value="approved" data-testid="files-tab-approved" className="gap-1.5 text-xs">Approved <span className="ml-1 rounded bg-muted px-1.5 text-[10px]">{counts.approved}</span></TabsTrigger>
        </TabsList>
      </Tabs>

      {visible.length === 0 ? (
        <Card>
          <CardContent className="py-12 text-center text-sm text-muted-foreground">No files in this view.</CardContent>
        </Card>
      ) : (
        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {visible.map(({ file: f, meta }) => (
            <Card
              key={f.id}
              className="cursor-pointer overflow-hidden transition hover:border-primary"
              onClick={() => setOpen(f)}
              data-testid={`file-card-${f.id}`}
            >
              {f.kind === "image" && f.src ? (
                <div className="aspect-video w-full overflow-hidden border-b bg-muted">
                  <img src={f.src} alt={f.name} className="size-full object-cover" />
                </div>
              ) : (
                <div className="grid aspect-video w-full place-items-center border-b bg-muted">
                  {f.kind === "image" ? <ImageIcon className="size-8 text-muted-foreground" /> : <FileText className="size-8 text-muted-foreground" />}
                </div>
              )}
              <CardContent className="p-4">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">{f.name}</p>
                    <p className="truncate text-[11px] text-muted-foreground">
                      {f.boardName ?? "Library"} · {f.uploadedBy} · {shortDate(f.uploadedAt)}
                    </p>
                  </div>
                  <Badge variant="outline" className={`text-[10px] ${META_BADGE[meta.status]}`}>
                    {meta.statusLabel}
                  </Badge>
                </div>
                <div className="mt-2 text-[11px] text-muted-foreground">
                  Approver: <span className="font-medium text-foreground">{meta.approverName}</span>{" "}
                  ({meta.approverRole})
                </div>
                <div className="mt-2 flex items-center gap-2 text-[11px] text-muted-foreground">
                  <span>v{f.version}</span>
                  <span>·</span>
                  <span>{f.size}</span>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <PreviewApprovalDialog file={open} onClose={() => setOpen(null)} role={role} />
    </div>
  );
}
