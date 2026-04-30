// Decide which hash route the SPA should boot into.
//
// Production rules (set by the WordPress companion plugin via window.OCD_CONFIG):
//   - logged-out  -> /#/login   (passwordless login CTA)
//   - admin       -> /#/admin   (Today / Boards / Clients / Settings)
//   - customer    -> /#/client  (Home / Boards / Files / Account)
//
// Escape hatches:
//   - ?preview=1 (or ?welcome=1) keeps the role picker at /#/welcome — useful
//     when the design team wants to demo the marketing card layout.
//   - An existing hash that points at a non-root route is preserved so that
//     deep links like /#/admin/boards/foo keep working after a refresh.
//   - data-ocd-role on the mount node wins over OCD_CONFIG when present, so
//     the `[oversee_admin_dashboard]` shortcode can force the admin surface
//     even for users whose effective role is otherwise customer.

export type OverseeBootRole = "guest" | "customer" | "admin";

export interface OverseeRuntimeConfig {
  isLoggedIn?: boolean;
  isAdmin?: boolean;
  role?: string;
  currentUser?: { id?: number } | null;
}

export interface BootResolverInput {
  config: OverseeRuntimeConfig | undefined;
  mountRole: string | null;
  search: string;
  currentHash: string;
}

const ADMIN_PREFIX = "#/admin";
const CLIENT_PREFIX = "#/client";
const LOGIN_PREFIX = "#/login";
const WELCOME_PREFIX = "#/welcome";

function isPreviewRequested(search: string): boolean {
  if (!search || typeof URLSearchParams === "undefined") return false;
  try {
    const qp = new URLSearchParams(search.startsWith("?") ? search.slice(1) : search);
    return qp.get("preview") === "1" || qp.get("welcome") === "1";
  } catch {
    return false;
  }
}

export function deriveRole(input: Pick<BootResolverInput, "config" | "mountRole">): OverseeBootRole {
  const explicit = (input.mountRole || "").toLowerCase().trim();
  if (explicit === "admin" || explicit === "customer" || explicit === "guest") {
    return explicit;
  }
  const cfg = input.config;
  if (!cfg) return "guest";
  if (typeof cfg.role === "string") {
    const r = cfg.role.toLowerCase();
    if (r === "admin" || r === "customer" || r === "guest") return r;
  }
  if (cfg.isLoggedIn === false) return "guest";
  if (cfg.isAdmin === true) return "admin";
  if (cfg.isLoggedIn === true) return "customer";
  return "guest";
}

export function resolveBootRoute(input: BootResolverInput): string {
  if (isPreviewRequested(input.search)) {
    return WELCOME_PREFIX;
  }

  const role = deriveRole(input);
  const hash = (input.currentHash || "").trim();

  // Preserve deep links into the appropriate surface after refresh.
  if (role === "admin" && hash.startsWith(ADMIN_PREFIX)) return hash;
  if (role === "customer" && hash.startsWith(CLIENT_PREFIX)) return hash;
  if (role === "guest" && hash.startsWith(LOGIN_PREFIX)) return hash;
  if (hash.startsWith(WELCOME_PREFIX)) return hash;

  switch (role) {
    case "admin":
      return ADMIN_PREFIX;
    case "customer":
      return CLIENT_PREFIX;
    case "guest":
    default:
      return LOGIN_PREFIX;
  }
}
