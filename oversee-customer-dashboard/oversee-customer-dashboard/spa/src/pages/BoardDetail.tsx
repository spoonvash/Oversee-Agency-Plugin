import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { apiGet } from "@/lib/api";

type BoardPayload = {
    board: { id: number; title: string; status: string };
    groups: { id: number; title: string; sort_order: number }[];
    items: {
        id: number; group_id: number; title: string; status: string;
        priority: string; due_date: string | null;
    }[];
    columns: { id: number; slug: string; label: string; kind: string }[];
};

export function BoardDetail() {
    const { id } = useParams();
    const [data, setData] = useState<BoardPayload | null>(null);
    const [view, setView] = useState<"table" | "kanban">("table");
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!id) return;
        apiGet<BoardPayload>(`boards/${id}`)
            .then(setData)
            .catch((err) => setError(err.message));
    }, [id]);

    if (error) return <div className="oversee-card p-6 text-sm text-red-600">{error}</div>;
    if (!data) return <div className="oversee-card p-6 text-sm text-zinc-500">Loading board…</div>;

    return (
        <div>
            <div className="flex items-center justify-between mb-4">
                <div>
                    <h1 className="text-xl font-semibold">{data.board.title}</h1>
                    <p className="text-xs text-zinc-500 capitalize">{data.board.status}</p>
                </div>
                <div className="flex gap-2">
                    <button
                        type="button"
                        className={view === "table" ? "oversee-btn-primary" : "oversee-btn-secondary"}
                        onClick={() => setView("table")}
                    >
                        Table
                    </button>
                    <button
                        type="button"
                        className={view === "kanban" ? "oversee-btn-primary" : "oversee-btn-secondary"}
                        onClick={() => setView("kanban")}
                    >
                        Kanban
                    </button>
                </div>
            </div>

            {view === "table" && <TableView data={data} />}
            {view === "kanban" && <KanbanView data={data} />}
        </div>
    );
}

function TableView({ data }: { data: BoardPayload }) {
    return (
        <div className="space-y-6">
            {data.groups.map((group) => {
                const items = data.items.filter((i) => i.group_id === group.id);
                return (
                    <div key={group.id} className="oversee-card overflow-hidden">
                        <div className="px-4 py-2 border-b text-sm font-medium" style={{ borderColor: "var(--oversee-border)" }}>
                            {group.title} · {items.length}
                        </div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-wider text-zinc-500">
                                    <th className="px-4 py-2">Task</th>
                                    <th className="px-4 py-2">Status</th>
                                    <th className="px-4 py-2">Priority</th>
                                    <th className="px-4 py-2">Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.length === 0 && (
                                    <tr><td className="px-4 py-3 text-zinc-500" colSpan={4}>No items in this group.</td></tr>
                                )}
                                {items.map((i) => (
                                    <tr key={i.id} className="border-t" style={{ borderColor: "var(--oversee-border)" }}>
                                        <td className="px-4 py-2">{i.title}</td>
                                        <td className="px-4 py-2 capitalize">{i.status.replace("_", " ")}</td>
                                        <td className="px-4 py-2 capitalize">{i.priority}</td>
                                        <td className="px-4 py-2">{i.due_date ?? "—"}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                );
            })}
        </div>
    );
}

function KanbanView({ data }: { data: BoardPayload }) {
    const lanes: { id: string; label: string }[] = [
        { id: "not_started", label: "Not started" },
        { id: "in_progress", label: "In progress" },
        { id: "in_review", label: "In review" },
        { id: "done", label: "Done" },
    ];
    return (
        <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${lanes.length}, minmax(0, 1fr))` }}>
            {lanes.map((lane) => {
                const items = data.items.filter((i) => i.status === lane.id);
                return (
                    <div key={lane.id} className="oversee-card p-3 min-h-[200px]">
                        <div className="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">
                            {lane.label} · {items.length}
                        </div>
                        <div className="space-y-2">
                            {items.map((i) => (
                                <div
                                    key={i.id}
                                    className="rounded-md p-2 text-sm"
                                    style={{ background: "var(--oversee-surface-2)", border: "1px solid var(--oversee-border)" }}
                                >
                                    <div className="font-medium">{i.title}</div>
                                    <div className="text-xs text-zinc-500 capitalize">{i.priority}</div>
                                </div>
                            ))}
                            {items.length === 0 && <div className="text-xs text-zinc-400">No items</div>}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
