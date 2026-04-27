// Thin wrapper around the WordPress REST API. The plugin emits a global
// OCD_CONFIG with the namespace URL and a nonce; we reuse that for every call.

declare global {
    interface Window {
        OCD_CONFIG?: {
            restUrl: string;
            nonce: string;
            isAdmin: boolean;
        };
    }
}

const config = window.OCD_CONFIG ?? {
    restUrl: "/wp-json/ocd/v1/",
    nonce: "",
    isAdmin: false,
};

export async function apiGet<T>(path: string): Promise<T> {
    const res = await fetch(config.restUrl.replace(/\/$/, "") + "/" + path.replace(/^\//, ""), {
        credentials: "include",
        headers: { "X-WP-Nonce": config.nonce },
    });
    if (!res.ok) {
        throw new ApiError(res.status, await safeText(res));
    }
    return (await res.json()) as T;
}

export async function apiPost<T>(path: string, body?: unknown): Promise<T> {
    const res = await fetch(config.restUrl.replace(/\/$/, "") + "/" + path.replace(/^\//, ""), {
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
