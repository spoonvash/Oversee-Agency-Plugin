// Designed document preview tile. Replaces blank gray boxes for PDF/doc/video files.
// Renders a stylized "page" surface with PDF icon, file name, page count/size, version,
// status, and a Review/View action. Images render the actual src in object-cover.
import { FileText, FileImage, Film, Eye, ArrowRight, CheckCircle2, RefreshCcw, ThumbsUp } from "lucide-react";
import type { FileEntry } from "@/lib/demo-store";
import { Badge } from "@/components/ui/badge";

const APPROVAL_TONE: Record<FileEntry["approval"], string> = {
  pending: "border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-900/60 dark:bg-orange-950/40 dark:text-orange-200",
  approved: "border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200",
 "changes-requested": "border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200",
 "n/a": "border-strong bg-muted text-zinc-700   dark:text-zinc-300",
};

const APPROVAL_LABEL: Record<FileEntry["approval"], string> = {
  pending: "Needs review",
  approved: "Approved",
 "changes-requested": "Changes requested",
 "n/a": "Reference",
};

const APPROVAL_ICON: Record<FileEntry["approval"], React.ComponentType<{ className?: string }>> = {
  pending: ThumbsUp,
  approved: CheckCircle2,
 "changes-requested": RefreshCcw,
 "n/a": FileImage,
};

function kindMeta(kind: FileEntry["kind"]) {
  if (kind === "pdf") return { label: "PDF", Icon: FileText, hue: "rose" };
  if (kind === "doc") return { label: "DOC", Icon: FileText, hue: "sky" };
  if (kind === "video") return { label: "VIDEO", Icon: Film, hue: "violet" };
  return { label: "IMAGE", Icon: FileImage, hue: "zinc" };
}

/** Inline mock document page for non-image files — replaces blank gray placeholders. */
export function DocumentMockPage({ file, compact = false }: { file: FileEntry; compact?: boolean }) {
  const meta = kindMeta(file.kind);
  const Icon = meta.Icon;

  // Estimate pages from size (just a visual cue — preview is mocked).
  const sizeMb = parseFloat(file.size) || 1;
  const pages = file.kind === "pdf" ? Math.max(2, Math.round(sizeMb * 2)) : null;

  return (
    <div
      className={`relative grid h-full w-full place-items-center overflow-hidden bg-gradient-to-br ${
        meta.hue === "rose"
          ? "from-rose-50 via-white to-rose-50/40 dark:from-rose-950/30 dark:via-zinc-950 dark:to-rose-950/10"
          : meta.hue === "sky"
          ? "from-sky-50 via-white to-sky-50/40 dark:from-sky-950/30 dark:via-zinc-950 dark:to-sky-950/10"
          : meta.hue === "violet"
          ? "from-violet-50 via-white to-violet-50/40 dark:from-violet-950/30 dark:via-zinc-950 dark:to-violet-950/10"
          : "from-zinc-50 via-white to-zinc-50/40 dark:from-zinc-900 dark:via-zinc-950 dark:to-zinc-900"
      }`}
    >
      {/* Faux paper page */}
      <div
        className={`relative flex flex-col gap-1.5 rounded-md border border-border/80 bg-white/95 shadow-sm /60 /80 ${
          compact ? "h-[78%] w-[58%] p-2" : "h-[80%] w-[55%] p-3"
        }`}
      >
        <div className={`flex items-center gap-1.5 ${compact ? "text-[8px]" : "text-[9px]"} font-semibold uppercase tracking-wider ${
          meta.hue === "rose" ? "text-rose-700 dark:text-rose-300"
            : meta.hue === "sky" ? "text-sky-700 dark:text-sky-300"
            : meta.hue === "violet" ? "text-violet-700 dark:text-violet-300"
            : "text-muted-foreground"
        }`}>
          <Icon className={compact ? "size-2.5" : "size-3"} />
          {meta.label}
        </div>
        <div className="space-y-1 pt-0.5">
          <div className="h-1 w-[80%] rounded bg-zinc-300/80 " />
          <div className="h-1 w-[65%] rounded bg-zinc-300/60 /70" />
        </div>
        <div className="mt-1 space-y-0.5">
          <div className="h-0.5 w-full rounded bg-muted " />
          <div className="h-0.5 w-[95%] rounded bg-muted " />
          <div className="h-0.5 w-[88%] rounded bg-muted " />
          <div className="h-0.5 w-[92%] rounded bg-muted " />
          <div className="h-0.5 w-[70%] rounded bg-muted " />
        </div>
        <div className="mt-1 space-y-0.5">
          <div className="h-0.5 w-[85%] rounded bg-muted " />
          <div className="h-0.5 w-[78%] rounded bg-muted " />
        </div>
      </div>
      {pages && (
        <div className="absolute bottom-2 right-2 rounded-md border border-border bg-white/90 px-1.5 py-0.5 text-[9px] font-medium text-zinc-600  /90 dark:text-zinc-300">
          {pages} pages
        </div>
      )}
    </div>
  );
}

/** Compact horizontal preview row — used in "Needs review" panels. */
export function PreviewListCard({
  file,
  role,
  onOpen,
}: {
  file: FileEntry;
  role: "client" | "admin";
  onOpen: () => void;
}) {
  const Icon = APPROVAL_ICON[file.approval];
  const ctaLabel =
    file.approval === "pending"
      ? role === "client"
        ? "Review & approve"
        : "Open preview"
      : "View";

  return (
    <button
      type="button"
      onClick={onOpen}
      className={`group flex w-full items-stretch gap-3 rounded-lg border bg-card p-2.5 text-left transition hover:shadow-md ${
        file.approval === "pending"
          ? "border-orange-300/80 hover:border-orange-400 dark:border-orange-900/60"
          : "border-border hover:border-strong "
      }`}
      data-testid={`preview-list-${file.id}`}
      aria-label={`Open preview ${file.name}`}
    >
      <div className="relative grid h-16 w-20 shrink-0 overflow-hidden rounded-md border border-border bg-zinc-100 ">
        {file.kind === "image" && file.src ? (
          <img src={file.src} alt={file.name} className="size-full object-cover" />
        ) : (
          <DocumentMockPage file={file} compact />
        )}
      </div>
      <div className="flex min-w-0 flex-1 flex-col justify-between gap-1 py-0.5">
        <div className="min-w-0">
          <p className="truncate text-[13px] font-semibold leading-snug" data-testid={`preview-list-name-${file.id}`}>
            {file.name}
          </p>
          <p className="truncate text-[11px] text-muted-foreground">
            {file.boardName || "—"} · v{file.version}
          </p>
        </div>
        <div className="flex items-center justify-between gap-2">
          <Badge
            variant="outline"
            className={`gap-1 text-[10px] font-medium ${APPROVAL_TONE[file.approval]}`}
          >
            <Icon className="size-2.5" />
            {APPROVAL_LABEL[file.approval]}
          </Badge>
          <span className={`inline-flex items-center gap-1 text-[11px] font-semibold ${
            file.approval === "pending"
              ? "text-orange-700 dark:text-orange-300"
              : "text-foreground"
          }`}>
            {ctaLabel}
            <ArrowRight className="size-3 transition group-hover:translate-x-0.5" />
          </span>
        </div>
      </div>
    </button>
  );
}

/** Larger card variant — consistent aspect ratio. Used on Client Files page. */
export function PreviewBigCard({
  file,
  role,
  onOpen,
}: {
  file: FileEntry;
  role: "client" | "admin";
  onOpen: () => void;
}) {
  const Icon = APPROVAL_ICON[file.approval];
  const ctaLabel =
    file.approval === "pending"
      ? role === "client"
        ? "Review & approve"
        : "Open preview"
      : "View";

  return (
    <button
      type="button"
      onClick={onOpen}
      className={`group flex flex-col overflow-hidden rounded-xl border bg-card text-left shadow-sm transition hover:shadow-md ${
        file.approval === "pending"
          ? "border-orange-300 hover:border-orange-400 dark:border-orange-900/60"
          : "border-border hover:border-strong "
      }`}
      data-testid={`preview-card-${file.id}`}
      aria-label={`Open preview ${file.name}`}
    >
      <div className="relative aspect-[4/3] w-full overflow-hidden bg-muted">
        {file.kind === "image" && file.src ? (
          <img src={file.src} alt={file.name} className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]" />
        ) : (
          <DocumentMockPage file={file} />
        )}
        <div className="absolute left-2 top-2">
          <Badge
            variant="outline"
            className={`gap-1 text-[10px] font-semibold uppercase tracking-wide backdrop-blur ${APPROVAL_TONE[file.approval]}`}
          >
            <Icon className="size-3" />
            {APPROVAL_LABEL[file.approval]}
          </Badge>
        </div>
      </div>
      <div className="flex flex-1 flex-col gap-1 px-4 py-3">
        <p className="truncate text-sm font-semibold leading-snug" data-testid={`preview-card-name-${file.id}`}>
          {file.name}
        </p>
        <p className="truncate text-[11px] text-muted-foreground">
          {file.boardName || "—"} · v{file.version} · {file.size}
        </p>
        <p className="truncate text-[11px] text-muted-foreground">
          Uploaded {new Date(file.uploadedAt).toLocaleDateString(undefined, { month: "short", day: "numeric" })} by {file.uploadedBy}
        </p>
      </div>
      <div
        className={`flex items-center justify-between gap-2 border-t px-4 py-2.5 text-xs font-semibold transition ${
          file.approval === "pending"
            ? "border-orange-200 bg-orange-50/60 text-orange-800 group-hover:bg-orange-100 dark:border-orange-900/60 dark:bg-orange-950/30 dark:text-orange-200 dark:group-hover:bg-orange-950/50"
            : "border-border bg-muted text-foreground group-hover:bg-muted/50 dark:group-hover:bg-zinc-900"
        }`}
      >
        <span className="inline-flex items-center gap-1.5">
          <Eye className="size-3.5" />
          {ctaLabel}
        </span>
        <ArrowRight className="size-3.5 transition group-hover:translate-x-0.5" />
      </div>
    </button>
  );
}
