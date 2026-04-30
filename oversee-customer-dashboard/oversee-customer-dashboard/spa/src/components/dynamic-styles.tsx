// Dynamic-style primitives that emit a scoped <style> element instead of using
// the React DOM `style` prop. Lets dynamic widths/positions/transforms render
// without inline style props (which the project bans).
//
// Each component generates a unique id (via React.useId) and renders a CSS rule
// that targets `[data-dyn-id="<id>"]` with the desired declaration. The rule is
// scoped to that single element, so there is no global leakage.
import { useId } from "react";

// ─────────────────────────────────────────────────────────────────────────────
// BarFill — fills a horizontal track to `pct` percent.
// Use inside any track element (e.g. h-1 w-20 rounded-full bg-muted).
// ─────────────────────────────────────────────────────────────────────────────
export function BarFill({
  pct,
  className = "block h-full bg-primary",
  testId,
}: {
  pct: number;
  className?: string;
  testId?: string;
}) {
  const id = useId();
  const clamped = Math.max(0, Math.min(100, pct));
  return (
    <>
      <style>{`[data-dyn-bar="${id}"]{width:${clamped}%}`}</style>
      <span data-dyn-bar={id} className={className} data-testid={testId} />
    </>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// PinDot — positions an absolutely-placed marker at (x%, y%) of its parent.
// Caller supplies the visual content as children.
// ─────────────────────────────────────────────────────────────────────────────
export function PinDot({
  x,
  y,
  className,
  testId,
  children,
}: {
  x: number;
  y: number;
  className?: string;
  testId?: string;
  children: React.ReactNode;
}) {
  const id = useId();
  const cx = Math.max(0, Math.min(100, x));
  const cy = Math.max(0, Math.min(100, y));
  return (
    <>
      <style>{`[data-dyn-pin="${id}"]{left:${cx}%;top:${cy}%}`}</style>
      <div data-dyn-pin={id} className={className} data-testid={testId}>
        {children}
      </div>
    </>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// PanZoomImage — image with translate(panX,panY) scale(zoom) transform.
// Scoped <style> instead of inline style prop.
// ─────────────────────────────────────────────────────────────────────────────
export function PanZoomImage({
  src,
  alt,
  panX,
  panY,
  zoom,
  className,
  testId,
}: {
  src: string;
  alt: string;
  panX: number;
  panY: number;
  zoom: number;
  className?: string;
  testId?: string;
}) {
  const id = useId();
  return (
    <>
      <style>{`[data-dyn-pz="${id}"]{transform:translate(${panX}px,${panY}px) scale(${zoom});transform-origin:center center;transition:transform .15s ease-out}`}</style>
      <img
        data-dyn-pz={id}
        src={src}
        alt={alt}
        className={className}
        data-testid={testId}
        draggable={false}
      />
    </>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// ProgressIndicator — replacement transform for shadcn Progress.
// Renders a translateX(-100% + value%) without the style prop.
// ─────────────────────────────────────────────────────────────────────────────
export function ProgressIndicator({
  value,
  className,
}: {
  value: number;
  className: string;
}) {
  const id = useId();
  const v = Math.max(0, Math.min(100, value));
  return (
    <>
      <style>{`[data-dyn-progress="${id}"]{transform:translateX(-${100 - v}%)}`}</style>
      <div data-dyn-progress={id} className={className} />
    </>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Swatch — small color swatch (e.g. chart legend dots) without style prop.
// ─────────────────────────────────────────────────────────────────────────────
export function Swatch({
  color,
  className = "h-2 w-2 shrink-0 rounded-[2px]",
}: {
  color: string;
  className?: string;
}) {
  const id = useId();
  return (
    <>
      <style>{`[data-dyn-sw="${id}"]{background-color:${color}}`}</style>
      <div data-dyn-sw={id} className={className} />
    </>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// BackgroundImage — set a background-image URL without using the style prop.
// `cover` is implied via Tailwind classes the caller applies.
// ─────────────────────────────────────────────────────────────────────────────
export function BackgroundImage({
  url,
  className,
  children,
  ariaHidden,
  testId,
}: {
  url: string;
  className?: string;
  children?: React.ReactNode;
  ariaHidden?: boolean;
  testId?: string;
}) {
  const id = useId();
  // CSS escape: keep simple URLs; consumer is responsible for trustworthy URLs.
  return (
    <>
      <style>{`[data-dyn-bg="${id}"]{background-image:url("${url}");background-size:cover;background-position:center}`}</style>
      <div
        data-dyn-bg={id}
        className={className}
        aria-hidden={ariaHidden}
        data-testid={testId}
      >
        {children}
      </div>
    </>
  );
}
