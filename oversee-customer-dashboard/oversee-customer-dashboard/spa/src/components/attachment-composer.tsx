// AttachmentComposer — single shared attachment control for composers.
//
// Replaces the duplicate Image/File buttons in the item-detail drawer with
// ONE working "Attach file or image" control backed by a real
// <input type="file"> (reachable by Playwright `setInputFiles`).
//
// No inline styles. No localStorage/sessionStorage. Object URLs are tracked
// and revoked on unmount or remove.
import { useCallback, useEffect, useRef, useState } from "react";
import {
  FileText,
  Image as ImageIcon,
  Paperclip,
  Video,
  X,
} from "lucide-react";
import { Button } from "@/components/ui/button";

export type DraftAttachment = {
  id: string;
  /** Image | Loom-like video | Generic file/doc */
  kind: "image" | "file" | "loom";
  /** Object URL for previews; for non-image files it's "#" or blob URL. */
  src: string;
  /** Visible filename / caption shown beside chip and posted to thread. */
  caption: string;
  /** Original mime type (informational, used for display). */
  mime?: string;
  /** Original byte size. */
  size?: number;
};

const DEFAULT_ACCEPT =
  "image/*,application/pdf,video/*,.doc,.docx,.txt,.zip,.csv,.xls,.xlsx,.ppt,.pptx";

function inferKind(file: File): DraftAttachment["kind"] {
  if (file.type.startsWith("image/")) return "image";
  return "file";
}

export function humanSize(bytes?: number): string {
  if (bytes === undefined || !Number.isFinite(bytes) || bytes < 0) return "—";
  if (bytes < 1024) return `${bytes} B`;
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb.toFixed(kb < 10 ? 1 : 0)} KB`;
  const mb = kb / 1024;
  return `${mb.toFixed(mb < 10 ? 1 : 0)} MB`;
}

type ChipsProps = {
  attachments: DraftAttachment[];
  onChange: (next: DraftAttachment[]) => void;
  testIdPrefix?: string;
};

/** Chip row of staged attachments (image thumb or file card) with remove [x]. */
export function AttachmentChips({
  attachments,
  onChange,
  testIdPrefix = "composer-attach",
}: ChipsProps) {
  if (attachments.length === 0) return null;
  const removeOne = (id: string) => {
    const target = attachments.find((a) => a.id === id);
    if (target && target.kind === "image" && target.src.startsWith("blob:")) {
      URL.revokeObjectURL(target.src);
    }
    onChange(attachments.filter((a) => a.id !== id));
  };
  return (
    <ul
      className="flex flex-wrap gap-2"
      data-testid={`${testIdPrefix}-chips`}
    >
      {attachments.map((a) => (
        <li
          key={a.id}
          className="group relative"
          data-testid={`${testIdPrefix}-chip-${a.id}`}
        >
          {a.kind === "image" ? (
            <div className="relative size-16 overflow-hidden rounded-md border border-border bg-muted">
              <img
                src={a.src}
                alt={a.caption}
                className="size-full object-cover"
              />
            </div>
          ) : (
            <div className="flex w-56 items-center gap-2 rounded-md border border-border bg-card px-2 py-1.5">
              <div className="grid size-8 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground">
                {a.mime?.startsWith("video/") ? (
                  <Video className="size-4" />
                ) : (
                  <FileText className="size-4" />
                )}
              </div>
              <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-medium">{a.caption}</p>
                <p className="text-[11px] text-muted-foreground">
                  {humanSize(a.size)}
                </p>
              </div>
            </div>
          )}
          <button
            type="button"
            onClick={() => removeOne(a.id)}
            aria-label={`Remove ${a.caption}`}
            className="absolute -right-1.5 -top-1.5 grid size-5 place-items-center rounded-full bg-zinc-900 text-white hover:bg-zinc-700"
            data-testid={`${testIdPrefix}-remove-${a.id}`}
          >
            <X className="size-3" />
          </button>
        </li>
      ))}
    </ul>
  );
}

type TriggerProps = {
  attachments: DraftAttachment[];
  onChange: (next: DraftAttachment[]) => void;
  /** Optional override of accepted file types. */
  accept?: string;
  /** Multiple selection (default true). */
  multiple?: boolean;
  /** data-testid prefix for input + add button. */
  testIdPrefix?: string;
  /** Label shown on the trigger button. */
  buttonLabel?: string;
};

/** Single "Attach file or image" trigger backed by a real <input type="file">. */
export function AttachmentTrigger({
  attachments,
  onChange,
  accept = DEFAULT_ACCEPT,
  multiple = true,
  testIdPrefix = "composer-attach",
  buttonLabel = "Attach file or image",
}: TriggerProps) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [ownedUrls, setOwnedUrls] = useState<string[]>([]);

  useEffect(() => {
    return () => {
      ownedUrls.forEach((u) => URL.revokeObjectURL(u));
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const stageFiles = useCallback(
    (fileList: FileList | File[]) => {
      const files = Array.from(fileList);
      if (files.length === 0) return;
      const next: DraftAttachment[] = [];
      const newUrls: string[] = [];
      for (const file of files) {
        const kind = inferKind(file);
        const url = kind === "image" ? URL.createObjectURL(file) : "#";
        if (kind === "image") newUrls.push(url);
        next.push({
          id: `att-${Date.now()}-${Math.random().toString(36).slice(2, 8)}-${idx(next.length)}`,
          kind,
          src: url,
          caption: file.name,
          mime: file.type,
          size: file.size,
        });
      }
      setOwnedUrls((prev) => [...prev, ...newUrls]);
      onChange([...attachments, ...next]);
    },
    [attachments, onChange],
  );

  const handleInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      stageFiles(e.target.files);
      e.target.value = "";
    }
  };

  return (
    <div>
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        multiple={multiple}
        onChange={handleInput}
        className="hidden"
        data-testid={`${testIdPrefix}-input`}
      />
      <Button
        type="button"
        variant="ghost"
        size="sm"
        className="h-7 gap-1 text-xs"
        onClick={() => inputRef.current?.click()}
        data-testid={`${testIdPrefix}-button`}
      >
        <Paperclip className="size-3.5" />
        {buttonLabel}
      </Button>
    </div>
  );
}

function idx(n: number): string {
  return n.toString(36);
}

/** Icon to render inline alongside a non-image attachment in a thread post. */
export function AttachmentFileIcon({ mime }: { mime?: string }) {
  if (mime?.startsWith("video/")) return <Video className="size-4" />;
  if (mime?.startsWith("image/")) return <ImageIcon className="size-4" />;
  return <FileText className="size-4" />;
}
