// Oversee brand mark + role-specific subtitle.
// CRITICAL: When tone === "sidebar", the subtitle must reflect the role.
// Client → "Client Portal", Admin → "Admin Console". Never "Customer Dashboard"
// for both — that was the bug users hit.

export function OverseeLogo({
  compact = false,
  tone = "auto",
  subtitle,
}: {
  compact?: boolean;
  tone?: "auto" | "sidebar" | "page";
  subtitle?: string;
}) {
  const titleClass =
    tone === "sidebar"
      ? "text-sidebar-foreground"
      : tone === "page"
        ? "text-foreground"
        : "text-foreground";
  const subClass =
    tone === "sidebar"
      ? "text-sidebar-foreground/60"
      : "text-muted-foreground";
  const finalSubtitle = subtitle ?? "Customer Dashboard";
  return (
    <div className="flex items-center gap-3" aria-label={`Oversee · ${finalSubtitle}`}>
      <svg
        className="size-9 shrink-0"
        viewBox="0 0 48 48"
        fill="none"
        role="img"
        aria-label="Oversee mark"
      >
        <rect width="48" height="48" rx="10" fill="#ff8201" />
        <path
          d="M10 24c4-8 8.6-12 14-12s10 4 14 12c-4 8-8.6 12-14 12s-10-4-14-12Z"
          stroke="white"
          strokeWidth="2.6"
          strokeLinejoin="round"
        />
        <circle cx="24" cy="24" r="4.4" fill="white" />
      </svg>
      {!compact && (
        <div className="grid leading-none">
          <span className={`text-base font-semibold tracking-[-0.02em] ${titleClass}`}>
            Oversee
          </span>
          <span className={`text-xs font-medium ${subClass}`}>
            {finalSubtitle}
          </span>
        </div>
      )}
    </div>
  );
}
