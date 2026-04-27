// Thin wrapper around the WordPress REST API. The plugin emits a global
// OCD_CONFIG with the namespace URL and a nonce; we reuse that for every call.
//
// Two namespaces coexist while we migrate the plugin to the spec layout:
//
//   - ocd/v1     — legacy namespace (default for back-compat).
//   - oversee/v1 — spec-aligned namespace (new endpoints from 2.x onwards).
//
// Pass `{ namespace: "oversee/v1" }` to use the spec namespace explicitly.

declare global {
    interface Window {
        OCD_CONFIG?: {
            restUrl: string;       // e.g. https://overseeagency.com/wp-json/ocd/v1/
            restRoot?: string;     // e.g. https://overseeagency.com/wp-json/
            nonce: string;
            isAdmin: boolean;
        };
    }
}

const config = window.OCD_CONFIG ?? {
    restUrl: "/wp-json/ocd/v1/",
    restRoot: "/wp-json/",
    nonce: "",
    isAdmin: false,
};

type Opts = { namespace?: string };

function buildUrl(path: string, opts?: Opts): string {
    if (opts?.namespace) {
        const root = (config.restRoot ?? config.restUrl.replace(/\/(ocd|oversee)\/v1\/?$/, "/")).replace(/\/$/, "");
        return `${root}/${opts.namespace}/${path.replace(/^\//, "")}`;
    }
    return config.restUrl.replace(/\/$/, "") + "/" + path.replace(/^\//, "");
}

export async function apiGet<T>(path: string, opts?: Opts): Promise<T> {
    const res = await fetch(buildUrl(path, opts), {
        credentials: "include",
        headers: { "X-WP-Nonce": config.nonce },
    });
    if (!res.ok) {
        throw new ApiError(res.status, await safeText(res));
    }
    return (await res.json()) as T;
}

export async function apiPost<T>(path: string, body?: unknown, opts?: Opts): Promise<T> {
    const res = await fetch(buildUrl(path, opts), {
        method: "POST",
        credentials: "include",
        headers: {
            "X-WP-Nonce": config.nonce,
            "Content-Type": "application/json",
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) {
        throw new ApiError(res.status, await safeText(res));
    }
    return (await res.json()) as T;
}

export class ApiError extends Error {
    constructor(public status: number, public detail: string) {
        super(`API error ${status}: ${detail}`);
    }
}

async function safeText(res: Response): Promise<string> {
    try {
        return await res.text();
    } catch {
        return "";
    }
}

export const isAdmin = config.isAdmin;
