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

    <div class="flex h-full flex-1 flex-col gap-8 p-6">
        <div class="grid gap-3 md:grid-cols-3">
            <Link
                href="/admin/agents"
                class="rounded-lg bg-muted/40 p-5 text-card-foreground transition hover:bg-muted"
            >
                <p class="text-sm font-medium text-muted-foreground">Agents</p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.agents }}</p>
            </Link>
            <Link
                href="/admin/task-types"
                class="rounded-lg bg-muted/40 p-5 text-card-foreground transition hover:bg-muted"
            >
                <p class="text-sm font-medium text-muted-foreground">
                    Task types
                </p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.taskTypes }}</p>
            </Link>
            <div class="rounded-lg bg-muted/40 p-5 text-card-foreground">
                <p class="text-sm font-medium text-muted-foreground">
                    Total timers
                </p>
                <p class="mt-2 text-3xl font-semibold">{{ stats.timers }}</p>
            </div>
        </div>

        <div class="text-card-foreground">
            <div class="mb-3">
                <h2 class="text-lg font-semibold">Recent completed timers</h2>
                <p class="text-sm text-muted-foreground">
                    Last 10 finished timers across all agents.
                </p>
            </div>
            <div class="overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="px-5">Agent</TableHead>
                            <TableHead class="px-5">Task type</TableHead>
                            <TableHead class="px-5 text-right">Hours</TableHead>
                            <TableHead class="px-5">Ended at</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty
                            v-if="recentTimers.length === 0"
                            :colspan="4"
                        >
                            No timers completed yet.
                        </TableEmpty>
                        <TableRow v-for="timer in recentTimers" :key="timer.id">
                            <TableCell class="px-5 py-4 font-medium">
                                {{ timer.agent }}
                            </TableCell>
                            <TableCell class="px-5 py-4">{{
                                timer.task_type
                            }}</TableCell>
                            <TableCell
                                class="px-5 py-4 text-right tabular-nums"
                            >
                                {{ timer.decimal_hours.toFixed(2) }}
                            </TableCell>
                            <TableCell class="px-5 py-4">{{
                                formatDate(timer.ended_at)
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>
</template>
