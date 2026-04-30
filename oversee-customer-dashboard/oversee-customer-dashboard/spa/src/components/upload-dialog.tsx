// UploadFileDialog — real file upload UX for the Oversee preview.
//
// Uses an actual <input type="file"> so it's reachable by Playwright's
// `setInputFiles` and by real users via click-to-browse or drag-and-drop.
// Each picked File is converted to a stable preview (image → object URL,
// document/video → metadata-only mock) and added to the shared demo store
// via `addFile` plus a feed event recording the upload.
//
// No localStorage / sessionStorage / cookies. No inline styles.
import { useCallback, useEffect, useRef, useState } from "react";
import { CloudUpload, FileText, Image as ImageIcon, Send, X, Trash2 } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { useDemoStore, type FileEntry } from "@/lib/demo-store";
import { useToast } from "@/hooks/use-toast";

type Props = {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  /** Optional board name to tag the upload with. */
  boardName?: string;
  /** Display name of the uploader (shown in file list). */
  uploaderName?: string;
  /** Called after a successful upload with the last new file id. */
  onUploaded?: (fileId: string) => void;
  /** Optional override label shown in the dialog title. */
  title?: string;
};

const ACCEPT =
  "image/*,application/pdf,video/*,.doc,.docx,.txt,.zip,.csv,.xls,.xlsx,.ppt,.pptx";

type Staged = {
  key: string;
  file: File;
  previewUrl?: string; // object URL for images
  kind: FileEntry["kind"];
};

function inferKind(file: File): FileEntry["kind"] {
  if (file.type.startsWith("image/")) return "image";
  if (file.type === "application/pdf" || /\.pdf$/i.test(file.name)) return "pdf";
  if (file.type.startsWith("video/")) return "video";
  return "doc";
}

function humanSize(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes < 0) return "—";
  if (bytes < 1024) return `${bytes} B`;
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb.toFixed(kb < 10 ? 1 : 0)} KB`;
  const mb = kb / 1024;
  return `${mb.toFixed(mb < 10 ? 1 : 0)} MB`;
}

export function UploadFileDialog({
  open,
  onOpenChange,
  boardName,
  uploaderName = "Maya Lin",
  onUploaded,
  title = "Share files with your Oversee team",
}: Props) {
  const { addFile } = useDemoStore();
  const { toast } = useToast();
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [staged, setStaged] = useState<Staged[]>([]);
  const [note, setNote] = useState("");
  const [isDragOver, setIsDragOver] = useState(false);

  const reset = useCallback(() => {
    setStaged((prev) => {
      prev.forEach((s) => {
        if (s.previewUrl) URL.revokeObjectURL(s.previewUrl);
      });
      return [];
    });
    setNote("");
    setIsDragOver(false);
  }, []);

  // Cleanup object URLs on unmount.
  useEffect(() => {
    return () => {
      staged.forEach((s) => {
        if (s.previewUrl) URL.revokeObjectURL(s.previewUrl);
      });
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const stageFiles = useCallback((fileList: FileList | File[]) => {
    const files = Array.from(fileList);
    if (files.length === 0) return;
    const next: Staged[] = files.map((file, idx) => {
      const kind = inferKind(file);
      const previewUrl = kind === "image" ? URL.createObjectURL(file) : undefined;
      return {
        key: `${file.name}-${file.size}-${file.lastModified}-${idx}-${Date.now()}`,
        file,
        previewUrl,
        kind,
      };
    });
    setStaged((prev) => [...prev, ...next]);
  }, []);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files.length > 0) {
      stageFiles(e.target.files);
      // Allow re-selecting the same file later.
      e.target.value = "";
    }
  };

  const removeStaged = (key: string) => {
    setStaged((prev) => {
      const target = prev.find((s) => s.key === key);
      if (target?.previewUrl) URL.revokeObjectURL(target.previewUrl);
      return prev.filter((s) => s.key !== key);
    });
  };

  const onDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragOver(false);
    if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
      stageFiles(e.dataTransfer.files);
    }
  };

  const submit = () => {
    if (staged.length === 0) {
      toast({
        title: "Pick a file first",
        description: "Click Browse or drop a file into the dropzone.",
      });
      return;
    }
    const now = new Date().toISOString();
    let lastId: string | null = null;
    staged.forEach((s) => {
      lastId = addFile({
        name: s.file.name,
        kind: s.kind,
        // For images, persist the object URL so the file list can show a real thumbnail.
        // Object URLs survive for the lifetime of the document — fine for the preview.
        src: s.previewUrl,
        size: humanSize(s.file.size),
        uploadedAt: now,
        uploadedBy: uploaderName,
        boardName,
        approval: "pending",
        version: 1,
      });
    });
    toast({
      title: `${staged.length} file${staged.length === 1 ? "" : "s"} uploaded`,
      description: note.trim()
        ? `Note: ${note.trim()}`
        : "Your team will see them in Files & previews.",
    });
    onUploaded?.(lastId ?? "");
    // Don't revoke object URLs here — the file list now references them.
    setStaged([]);
    setNote("");
    onOpenChange(false);
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(v) => {
        if (!v) reset();
        onOpenChange(v);
      }}
    >
      <DialogContent
        className="max-w-xl gap-0 overflow-hidden p-0"
        data-testid="dialog-upload"
      >
        <DialogHeader className="space-y-1 border-b border-border bg-muted/40 px-6 py-4">
          <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-muted-foreground">
            <CloudUpload className="size-3.5" /> Upload files
          </p>
          <DialogTitle className="text-lg">{title}</DialogTitle>
          <p className="text-xs text-muted-foreground">
            Drag and drop, or click Browse to pick files from your device. Image previews are
            generated locally — nothing leaves your browser.
          </p>
        </DialogHeader>

        <div className="space-y-4 px-6 py-4">
          {/* Real, accessible file input. Hidden via Tailwind class only — Playwright can still call setInputFiles on it. */}
          <input
            ref={inputRef}
            type="file"
            multiple
            accept={ACCEPT}
            onChange={handleInputChange}
            className="sr-only"
            data-testid="input-upload-file"
            aria-label="Choose files to upload"
          />

          <div
            onDragOver={(e) => {
              e.preventDefault();
              setIsDragOver(true);
            }}
            onDragLeave={() => setIsDragOver(false)}
            onDrop={onDrop}
            onClick={() => inputRef.current?.click()}
            onKeyDown={(e) => {
              if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                inputRef.current?.click();
              }
            }}
            role="button"
            tabIndex={0}
            data-testid="upload-dropzone"
            className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-10 text-center transition ${
              isDragOver
                ? "border-primary bg-primary/5"
                : "border-border bg-muted/30 hover:border-primary/60 hover:bg-muted/50"
            }`}
          >
            <CloudUpload className="size-7 text-muted-foreground" />
            <p className="text-sm font-medium">
              Drop files here, or <span className="text-primary underline-offset-2 hover:underline">browse</span>
            </p>
            <p className="text-[11px] text-muted-foreground">
              Images, PDF, video, docs — up to a few MB each in preview.
            </p>
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="mt-2 gap-1"
              onClick={(e) => {
                e.stopPropagation();
                inputRef.current?.click();
              }}
              data-testid="button-upload-browse"
            >
              <CloudUpload className="size-3.5" /> Browse files
            </Button>
          </div>

          {staged.length > 0 && (
            <section data-testid="upload-staged-list">
              <p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                Ready to upload ({staged.length})
              </p>
              <ul className="space-y-2">
                {staged.map((s) => (
                  <li
                    key={s.key}
                    className="flex items-center gap-3 rounded-md border border-border bg-card p-2"
                    data-testid={`upload-staged-${s.file.name}`}
                  >
                    <div className="grid size-12 shrink-0 place-items-center overflow-hidden rounded-md border border-border bg-muted">
                      {s.kind === "image" && s.previewUrl ? (
                        <img
                          src={s.previewUrl}
                          alt={s.file.name}
                          className="size-full object-cover"
                        />
                      ) : (
                        <FileText className="size-5 text-muted-foreground" />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">{s.file.name}</p>
                      <p className="text-[11px] text-muted-foreground">
                        {humanSize(s.file.size)} · {s.kind}
                      </p>
                    </div>
                    {s.kind === "image" && (
                      <span className="grid size-7 place-items-center rounded-md bg-primary/10 text-primary">
                        <ImageIcon className="size-3.5" />
                      </span>
                    )}
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      className="h-7 gap-1 text-xs"
                      onClick={() => removeStaged(s.key)}
                      data-testid={`button-remove-staged-${s.file.name}`}
                    >
                      <Trash2 className="size-3.5" />
                    </Button>
                  </li>
                ))}
              </ul>
            </section>
          )}

          <section>
            <label
              className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted-foreground"
              htmlFor="upload-note"
            >
              Note for your team (optional)
            </label>
            <Textarea
              id="upload-note"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="Anything we should know about these?"
              className="min-h-[64px] resize-none text-sm"
              data-testid="textarea-upload-note"
            />
          </section>
        </div>

        <DialogFooter className="flex flex-col-reverse gap-2 border-t border-border bg-muted/40 px-6 py-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-[11px] text-muted-foreground">
            Preview: image thumbnails generated via in-browser object URLs.
          </p>
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                reset();
                onOpenChange(false);
              }}
              data-testid="button-upload-cancel"
            >
              <X className="size-3.5" /> Cancel
            </Button>
            <Button
              size="sm"
              onClick={submit}
              className="gap-1"
              data-testid="button-upload-submit"
              disabled={staged.length === 0}
            >
              <Send className="size-3.5" /> Upload
              {staged.length > 0 && (
                <span className="ml-1 rounded bg-primary-foreground/20 px-1.5 py-0.5 text-[10px] tabular-nums">
                  {staged.length}
                </span>
              )}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
