import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { apiGet } from "@/lib/api";

type Board = {
    id: number;
    title: string;
    sku: string | null;
    status: string;
    updated_at: string;
};

export function Home() {
    const [boards, setBoards] = useState<Board[] | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        apiGet<{ boards: Board[] }>("boards")
            .then((res) => setBoards(res.boards))
            .catch((err) => setError(err.message));
    }, []);

    return (
        <div className="space-y-4">
            <h1 className="text-xl font-semibold">Welcome back</h1>
            <p className="text-sm text-zinc-500 dark:text-zinc-400">
                Quick overview of your active projects, recent activity, and open tasks.
            </p>

            <section className="oversee-card p-5">
                <h2 className="font-medium mb-3">Active boards</h2>
                {error && <p className="text-sm text-red-600">{error}</p>}
                {!boards && !error && <Skeleton />}
                {boards && boards.length === 0 && (
                    <p className="text-sm text-zinc-500">No active boards yet. Your account manager will spin one up after kickoff.</p>
                )}
                {boards && boards.length > 0 && (
                    <ul className="space-y-2">
                        {boards.map((b) => (
                            <li key={b.id} className="flex items-center justify-between border-t pt-2" style={{ borderColor: "var(--oversee-border)" }}>
                                <Link to={`/projects/${b.id}`} className="text-sm font-medium hover:underline">
                                    {b.title || `Board #${b.id}`}
                                </Link>
                                <span className="text-xs text-zinc-500 capitalize">{b.status}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

function Skeleton() {
    return (
        <div className="space-y-2">
            {[0, 1, 2].map((i) => (
                <div
                    key={i}
                    className="h-8 rounded-md"
                    style={{ background: "var(--oversee-surface-2)" }}
                />
            ))}
        </div>
    );
}
