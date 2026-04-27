import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { apiGet } from "@/lib/api";

type Board = { id: number; title: string; sku: string | null; status: string; updated_at: string };

export function Projects() {
    const [boards, setBoards] = useState<Board[] | null>(null);
    useEffect(() => {
        apiGet<{ boards: Board[] }>("boards").then((r) => setBoards(r.boards)).catch(() => setBoards([]));
    }, []);
    return (
        <div>
            <h1 className="text-xl font-semibold mb-4">My projects</h1>
            <div className="oversee-card overflow-hidden">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wider text-zinc-500">
                            <th className="px-4 py-3">Project</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        {boards == null && (
                            <tr><td className="px-4 py-6 text-zinc-500" colSpan={3}>Loading…</td></tr>
                        )}
                        {boards && boards.length === 0 && (
                            <tr><td className="px-4 py-6 text-zinc-500" colSpan={3}>No active projects yet.</td></tr>
                        )}
                        {boards && boards.map((b) => (
                            <tr key={b.id} className="border-t" style={{ borderColor: "var(--oversee-border)" }}>
                                <td className="px-4 py-3">
                                    <Link to={`/projects/${b.id}`} className="font-medium hover:underline">{b.title}</Link>
                                </td>
                                <td className="px-4 py-3 capitalize">{b.status}</td>
                                <td className="px-4 py-3 text-zinc-500">{b.updated_at}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
