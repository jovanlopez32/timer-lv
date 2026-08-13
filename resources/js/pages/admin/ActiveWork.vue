<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';

type TaskType = { id: number; name: string; brand: string | null };

type AgentWork = {
    id: number;
    agent: string;
    agent_slug: string;
    task_type_id: number;
    task_type: string;
    status: 'in_progress' | 'on_hold' | 'parked';
    started_at: string | null;
    elapsed_seconds: number;
};

const props = defineProps<{ agents: AgentWork[]; taskTypes: TaskType[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Active work', href: '/admin/active-work' },
        ],
    },
});

const statusLabel = (status: AgentWork['status']) => {
    if (status === 'in_progress') {
        return 'In progress';
    }
    if (status === 'parked') {
        return 'Parked';
    }
    return 'Paused';
};

// Blue = actively running, yellow = paused mid-task, gray = parked/stashed.
// Cards lean on these same colors (as soft tints) instead of borders so the
// status reads at a glance without extra chrome.
const statusDotClass = (status: AgentWork['status']) => {
    if (status === 'in_progress') {
        return 'bg-blue-500';
    }
    if (status === 'parked') {
        return 'bg-gray-400';
    }
    return 'bg-yellow-500';
};

const statusCardClass = (status: AgentWork['status']) => {
    if (status === 'in_progress') {
        return 'bg-blue-500/10 dark:bg-blue-500/15';
    }
    if (status === 'parked') {
        return 'bg-muted';
    }
    return 'bg-yellow-500/10 dark:bg-yellow-500/15';
};

const formatDate = (iso: string | null) => {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString();
};

const formatDuration = (seconds: number) => {
    const total = Math.max(0, seconds);
    const h = Math.floor(total / 3600)
        .toString()
        .padStart(2, '0');
    const m = Math.floor((total % 3600) / 60)
        .toString()
        .padStart(2, '0');
    const s = (total % 60).toString().padStart(2, '0');

    return `${h}:${m}:${s}`;
};

const parseDuration = (value: string): number | null => {
    const match = value.trim().match(/^(\d{1,4}):([0-5]?\d):([0-5]?\d)$/);

    if (!match) {
        return null;
    }

    return Number(match[1]) * 3600 + Number(match[2]) * 60 + Number(match[3]);
};

// Task type names repeat across brands, so the picker groups them by project.
const taskTypesByBrand = computed(() => {
    const groups = new Map<string, TaskType[]>();

    props.taskTypes.forEach((taskType) => {
        const brand = taskType.brand ?? 'No project';

        groups.set(brand, [...(groups.get(brand) ?? []), taskType]);
    });

    return [...groups.entries()].map(([brand, taskTypes]) => ({
        brand,
        taskTypes,
    }));
});

const editing = ref<AgentWork | null>(null);
const durationError = ref('');
const editForm = ref({
    task_type_id: '',
    total_time: '',
});

const openEdit = (item: AgentWork) => {
    editing.value = item;
    durationError.value = '';
    editForm.value = {
        task_type_id: String(item.task_type_id),
        total_time: formatDuration(item.elapsed_seconds),
    };
};

const saveEdit = () => {
    if (!editing.value) {
        return;
    }

    const elapsedSeconds = parseDuration(editForm.value.total_time);

    if (elapsedSeconds === null) {
        durationError.value = 'Use the HH:MM:SS format, e.g. 01:30:00.';

        return;
    }

    durationError.value = '';

    router.patch(
        `/admin/active-work/${editing.value.id}`,
        {
            task_type_id: editForm.value.task_type_id,
            elapsed_seconds: elapsedSeconds,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = null;
            },
        },
    );
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
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <div
                v-for="item in agents"
                :key="item.id"
                :class="[
                    'space-y-2.5 rounded-xl p-3.5 text-card-foreground',
                    statusCardClass(item.status),
                ]"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="truncate text-sm font-semibold">
                        {{ item.agent }}
                    </p>
                    <span
                        class="inline-flex shrink-0 items-center gap-1.5 text-[11px] font-medium text-muted-foreground"
                    >
                        <span class="relative flex h-3.5 w-3.5">
                            <span
                                v-if="item.status === 'in_progress'"
                                class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                                :class="statusDotClass(item.status)"
                            />
                            <span
                                class="relative inline-flex h-3.5 w-3.5 rounded-full"
                                :class="statusDotClass(item.status)"
                            />
                        </span>
                        {{ statusLabel(item.status) }}
                    </span>
                </div>

                <div class="space-y-0.5 text-xs text-muted-foreground">
                    <p class="truncate">
                        {{ item.task_type }}
                    </p>
                    <p>{{ formatDate(item.started_at) }}</p>
                </div>

                <div class="flex items-center justify-between gap-2 pt-0.5">
                    <span
                        class="font-mono text-lg font-semibold tabular-nums"
                    >
                        {{ formatDuration(item.elapsed_seconds) }}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7"
                        title="Edit"
                        @click="openEdit(item)"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        <span class="sr-only">Edit</span>
                    </Button>
                </div>
            </div>
        </section>

        <Dialog
            :open="editing !== null"
            @update:open="(open) => !open && (editing = null)"
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit active task</DialogTitle>
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="active-task-type">Task type</Label>
                        <Select
                            v-model="editForm.task_type_id"
                            name="task_type_id"
                        >
                            <SelectTrigger id="active-task-type">
                                <SelectValue placeholder="Select task type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup
                                    v-for="group in taskTypesByBrand"
                                    :key="group.brand"
                                >
                                    <SelectLabel>{{ group.brand }}</SelectLabel>
                                    <SelectItem
                                        v-for="taskType in group.taskTypes"
                                        :key="taskType.id"
                                        :value="String(taskType.id)"
                                    >
                                        {{ taskType.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="active-total-time">Total time</Label>
                        <Input
                            id="active-total-time"
                            v-model="editForm.total_time"
                            class="font-mono tabular-nums"
                            placeholder="HH:MM:SS"
                            inputmode="numeric"
                        />
                        <p
                            v-if="durationError"
                            class="text-sm text-destructive"
                        >
                            {{ durationError }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground">
                            Adjusts the latest session so the timer adds up to
                            this total.
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="editing = null"
                    >
                        Cancel
                    </Button>
                    <Button type="button" @click="saveEdit">
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
