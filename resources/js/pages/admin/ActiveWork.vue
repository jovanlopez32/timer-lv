<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

type AgentWork = {
    id: number;
    agent: string;
    agent_slug: string;
    task_type: string;
    status: 'in_progress' | 'on_hold';
    started_at: string | null;
};

defineProps<{ agents: AgentWork[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Active work', href: '/admin/active-work' },
        ],
    },
});

const statusLabel = (status: AgentWork['status']) =>
    status === 'in_progress' ? 'In progress' : 'On hold';

const statusVariant = (status: AgentWork['status']) =>
    status === 'in_progress' ? 'default' : 'secondary';

const formatDate = (iso: string | null) => {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString();
};
</script>

<template>
    <Head title="Active Work" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Active work</h1>
            <p class="text-sm text-muted-foreground">
                Agents currently working and their timer status.
            </p>
        </header>

        <section
            v-if="agents.length === 0"
            class="rounded-xl border border-dashed bg-card p-10 text-center text-sm text-muted-foreground"
        >
            No agents have active tasks right now.
        </section>

        <section
            v-else
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <Card
                v-for="item in agents"
                :key="item.id"
            >
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-3">
                        <CardTitle class="text-base">
                            {{ item.agent }}
                        </CardTitle>
                        <Badge :variant="statusVariant(item.status)">
                            {{ statusLabel(item.status) }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-1 text-sm text-muted-foreground">
                    <p>
                        Task type:
                        <span class="font-medium text-foreground">
                            {{ item.task_type }}
                        </span>
                    </p>
                    <p>
                        Started:
                        <span class="font-medium text-foreground">
                            {{ formatDate(item.started_at) }}
                        </span>
                    </p>
                </CardContent>
            </Card>
        </section>
    </div>
</template>
