# Oversee Dashboard SPA

Vite + React 19 + TypeScript + Tailwind v4 SPA mounted at `/dashboard/` by the Oversee Customer Dashboard plugin.

## Build

```sh
cd spa
npm install
npm run build      # outputs to ../assets/build (manifest + hashed JS/CSS)
npm run dev        # local Vite dev server (point WP at it via define('OCD_SPA_DEV_URL', 'http://localhost:5173'))
npm run typecheck  # tsc --noEmit
```

The plugin's `OCD_Assets` class reads `assets/build/.vite/manifest.json` (or the dev URL when set) and emits `<script type="module">` plus the hashed CSS link tag.

## Architecture

- `src/main.tsx` — root bootstrap. Mounts into `#oversee-dashboard-root` with `BrowserRouter basename="/dashboard"`.
- `src/App.tsx` — top-level layout (sidebar + topbar) and route table.
- `src/components/Sidebar.tsx` — collapsible nav. Picks client vs admin nav based on `OCD_CONFIG.isAdmin`.
- `src/components/Topbar.tsx` — breadcrumb, Cmd+K command palette stub, notification bell, dark mode toggle.
- `src/components/EmbedFrame.tsx` — generic HighLevel iframe wrapper that handles the unconfigured/no-contact empty states.
- `src/pages/*.tsx` — route bodies. `BoardDetail.tsx` renders the table + kanban views.
- `src/lib/api.ts` — REST wrapper using `OCD_CONFIG.restUrl` + nonce.
- `src/lib/theme.ts` — dark mode hook (server-persisted via `oversee_dark_mode` user meta).
- `src/lib/nav.ts` — sidebar navigation per the user's spec (12 client / 13 admin items).

## Brand language

Light mode — white/near-white surfaces, orange accent (#ff8201).
Dark mode — black/zinc surfaces, orange accent.
Orange is reserved for primary buttons, the active nav item, focus rings, and links. Surfaces are neutral so the orange reads as deliberate.

## Limitations / next steps

- The Cmd+K command palette is a UI shell; wire it to a `/search` endpoint when the back-end gains one.
- The TipTap editor and Pusher realtime client are scaffolded as dependencies but the integration is left to follow-up commits to keep this PR reviewable.
- Recharts is bundled but the only chart used today is a stub on Performance Reports (the embed handles real charts).
- The drag/drop reorder REST endpoint is implemented (`/items/<id>/move`) but the Kanban view doesn't yet wire `@dnd-kit` to it — it renders read-only lanes.
