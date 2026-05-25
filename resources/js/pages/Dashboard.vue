<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type RecentTimer = {
    id: number;
    agent: string;
    task_type: string;
    decimal_hours: number;
    ended_at: string | null;
};

defineProps<{
    stats: {
        agents: number;
        taskTypes: number;
        timers: number;
    };
    recentTimers: RecentTimer[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const formatDate = (iso: string | null) => {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString();
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div class="grid gap-4 md:grid-cols-3">
            <Link
                href="/admin/agents"
                class="rounded-xl border bg-card p-6 text-card-foreground shadow-sm transition hover:bg-accent/40"
            >
                <p class="text-sm font-medium text-muted-foreground">Agents</p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.agents }}</p>
            </Link>
            <Link
                href="/admin/task-types"
                class="rounded-xl border bg-card p-6 text-card-foreground shadow-sm transition hover:bg-accent/40"
            >
                <p class="text-sm font-medium text-muted-foreground">
                    Task types
                </p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.taskTypes }}</p>
            </Link>
            <div
                class="rounded-xl border bg-card p-6 text-card-foreground shadow-sm"
            >
                <p class="text-sm font-medium text-muted-foreground">
                    Total timers
                </p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.timers }}</p>
            </div>
        </div>

        <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <div class="border-b p-4">
                <h2 class="text-lg font-semibold">Recent completed timers</h2>
                <p class="text-sm text-muted-foreground">
                    Last 10 finished timers across all agents.
                </p>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Agent</TableHead>
                        <TableHead>Task type</TableHead>
                        <TableHead class="text-right">Hours</TableHead>
                        <TableHead>Ended at</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="recentTimers.length === 0" :colspan="4">
                        No timers completed yet.
                    </TableEmpty>
                    <TableRow v-for="timer in recentTimers" :key="timer.id">
                        <TableCell class="font-medium">
                            {{ timer.agent }}
                        </TableCell>
                        <TableCell>{{ timer.task_type }}</TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ timer.decimal_hours.toFixed(2) }}
                        </TableCell>
                        <TableCell>{{ formatDate(timer.ended_at) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
