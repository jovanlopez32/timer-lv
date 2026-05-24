<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

type TaskTypeStats = {
    id: number;
    name: string;
    count: number;
    average: number | null;
    median: number | null;
    min: number | null;
    max: number | null;
};

type BrandStats = {
    id: number;
    name: string;
    task_types: TaskTypeStats[];
};

defineProps<{ brands: BrandStats[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Stats', href: '/admin/stats' },
        ],
    },
});

const format = (value: number | null) =>
    value === null ? '—' : value.toFixed(2);
</script>

<template>
    <Head title="Stats" />

    <div class="flex flex-1 flex-col gap-8 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Stats</h1>
            <p class="text-sm text-muted-foreground">
                Hours per task type. Numbers come from completed timers only.
            </p>
        </header>

        <div
            v-if="brands.length === 0"
            class="rounded-xl border border-dashed bg-card p-10 text-center text-sm text-muted-foreground"
        >
            No brands yet. Create a brand to see stats.
        </div>

        <section
            v-for="brand in brands"
            :key="brand.id"
            class="space-y-4"
        >
            <div class="flex items-baseline justify-between">
                <h2 class="text-lg font-semibold">{{ brand.name }}</h2>
                <span class="text-xs uppercase tracking-wider text-muted-foreground">
                    {{ brand.task_types.length }}
                    {{ brand.task_types.length === 1 ? 'task type' : 'task types' }}
                </span>
            </div>

            <div
                v-if="brand.task_types.length === 0"
                class="rounded-lg border border-dashed bg-card p-6 text-sm text-muted-foreground"
            >
                This brand has no task types.
            </div>

            <div
                v-else
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="taskType in brand.task_types"
                    :key="taskType.id"
                    class="rounded-xl border bg-card p-5 text-card-foreground shadow-sm"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="truncate font-medium">
                            {{ taskType.name }}
                        </h3>
                        <span
                            class="shrink-0 text-xs uppercase tracking-wider text-muted-foreground"
                        >
                            {{ taskType.count }}
                            {{ taskType.count === 1 ? 'timer' : 'timers' }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-muted-foreground">
                                Average
                            </dt>
                            <dd class="mt-1 font-mono text-xl tabular-nums">
                                {{ format(taskType.average) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-muted-foreground">
                                Median
                            </dt>
                            <dd class="mt-1 font-mono text-xl tabular-nums">
                                {{ format(taskType.median) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-muted-foreground">
                                Min
                            </dt>
                            <dd class="mt-1 font-mono text-xl tabular-nums">
                                {{ format(taskType.min) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-muted-foreground">
                                Max
                            </dt>
                            <dd class="mt-1 font-mono text-xl tabular-nums">
                                {{ format(taskType.max) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
    </div>
</template>
