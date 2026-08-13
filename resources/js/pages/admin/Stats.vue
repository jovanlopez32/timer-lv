<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import type { CalendarDate } from '@internationalized/date';
import { CalendarIcon, ChevronsUpDown, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
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
    color: string;
    task_types: TaskTypeStats[];
};

type AgentOption = {
    id: number;
    name: string;
    brand: string | null;
};

type Filters = {
    from: string | null;
    to: string | null;
    agent_ids: number[];
};

const props = defineProps<{
    brands: BrandStats[];
    agents: AgentOption[];
    filters: Filters;
}>();

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

// Cards lean on the brand's own color as a soft tint instead of a border to
// keep sections visually distinct without adding extra chrome.
const brandCardStyle = (color: string) => ({
    backgroundColor: `${color}1A`,
});

const parseISODate = (value: string | null): CalendarDate | undefined => {
    if (!value) {
        return undefined;
    }

    try {
        return parseDate(value);
    } catch {
        return undefined;
    }
};

const dateRange = ref<any>({
    start: parseISODate(props.filters.from),
    end: parseISODate(props.filters.to),
});
const calendarOpen = ref(false);
const initialRange = today(getLocalTimeZone());

const selectedAgentIds = ref<number[]>([...props.filters.agent_ids]);
const agentPickerOpen = ref(false);

const formatRangeLabel = computed(() => {
    if (!dateRange.value.start && !dateRange.value.end) {
        return 'All time';
    }

    const tz = getLocalTimeZone();
    const start = dateRange.value.start
        ? dateRange.value.start.toDate(tz).toLocaleDateString()
        : '...';
    const end = dateRange.value.end
        ? dateRange.value.end.toDate(tz).toLocaleDateString()
        : '...';

    return `${start} → ${end}`;
});

const agentPickerLabel = computed(() => {
    if (selectedAgentIds.value.length === 0) {
        return 'All agents';
    }

    if (selectedAgentIds.value.length === 1) {
        return (
            props.agents.find((a) => a.id === selectedAgentIds.value[0])
                ?.name ?? 'All agents'
        );
    }

    return `${selectedAgentIds.value.length} agents selected`;
});

const selectAllAgents = () => {
    selectedAgentIds.value = [];
};

const toggleAgent = (id: number) => {
    const index = selectedAgentIds.value.indexOf(id);

    if (index === -1) {
        selectedAgentIds.value.push(id);
    } else {
        selectedAgentIds.value.splice(index, 1);
    }
};

const search = () => {
    router.get(
        '/admin/stats',
        {
            from: dateRange.value.start
                ? dateRange.value.start.toString()
                : undefined,
            to: dateRange.value.end
                ? dateRange.value.end.toString()
                : undefined,
            agent_ids: selectedAgentIds.value,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const reset = () => {
    dateRange.value = { start: undefined, end: undefined };
    selectedAgentIds.value = [];
    router.get(
        '/admin/stats',
        {},
        { preserveState: true, preserveScroll: true },
    );
};
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

        <section class="text-card-foreground">
            <div
                class="grid gap-4 md:grid-cols-[1fr_1fr_auto_auto] md:items-end"
            >
                <div class="grid gap-2">
                    <Label>Date range</Label>
                    <Popover v-model:open="calendarOpen">
                        <PopoverTrigger as-child>
                            <Button
                                variant="outline"
                                class="justify-start font-normal"
                            >
                                <CalendarIcon class="mr-2 h-4 w-4" />
                                <span>{{ formatRangeLabel }}</span>
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-auto p-0" align="start">
                            <RangeCalendar
                                v-model="dateRange"
                                :placeholder="initialRange"
                                :number-of-months="2"
                                weekday-format="short"
                            />
                        </PopoverContent>
                    </Popover>
                </div>

                <div class="grid gap-2">
                    <Label>Agents</Label>
                    <Popover v-model:open="agentPickerOpen">
                        <PopoverTrigger as-child>
                            <Button
                                variant="outline"
                                role="combobox"
                                :aria-expanded="agentPickerOpen"
                                class="justify-between font-normal"
                            >
                                <span class="truncate">{{
                                    agentPickerLabel
                                }}</span>
                                <ChevronsUpDown
                                    class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent
                            class="w-[--reka-popover-trigger-width] p-0"
                        >
                            <Command>
                                <CommandInput placeholder="Search agent..." />
                                <CommandList>
                                    <CommandGroup>
                                        <CommandItem
                                            value="all-agents"
                                            @select.prevent="selectAllAgents"
                                        >
                                            <Checkbox
                                                :model-value="
                                                    selectedAgentIds.length ===
                                                    0
                                                "
                                                class="mr-2"
                                            />
                                            All agents
                                        </CommandItem>
                                        <CommandItem
                                            v-for="agent in agents"
                                            :key="agent.id"
                                            :value="agent.name"
                                            @select.prevent="
                                                toggleAgent(agent.id)
                                            "
                                        >
                                            <Checkbox
                                                :model-value="
                                                    selectedAgentIds.includes(
                                                        agent.id,
                                                    )
                                                "
                                                class="mr-2"
                                            />
                                            <span class="truncate">{{
                                                agent.name
                                            }}</span>
                                            <span
                                                v-if="agent.brand"
                                                class="ml-2 truncate text-xs text-muted-foreground"
                                            >
                                                {{ agent.brand }}
                                            </span>
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>

                <Button type="button" @click="search">
                    <Search class="mr-2 h-4 w-4" />
                    Search
                </Button>

                <Button type="button" variant="ghost" @click="reset">
                    <X class="mr-2 h-4 w-4" />
                    Reset
                </Button>
            </div>
        </section>

        <div
            v-if="brands.length === 0"
            class="rounded-xl border border-dashed bg-card p-10 text-center text-sm text-muted-foreground"
        >
            No brands yet. Create a brand to see stats.
        </div>

        <section v-for="brand in brands" :key="brand.id" class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: brand.color }"
                    />
                    <h2 class="text-lg font-semibold">{{ brand.name }}</h2>
                </div>
                <span
                    class="text-xs tracking-wider text-muted-foreground uppercase"
                >
                    {{ brand.task_types.length }}
                    {{
                        brand.task_types.length === 1
                            ? 'task type'
                            : 'task types'
                    }}
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
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="taskType in brand.task_types"
                    :key="taskType.id"
                    class="rounded-xl p-4 text-card-foreground"
                    :style="brandCardStyle(brand.color)"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="truncate text-sm font-medium">
                            {{ taskType.name }}
                        </h3>
                        <span
                            class="shrink-0 text-[11px] tracking-wider text-muted-foreground uppercase"
                        >
                            {{ taskType.count }}
                            {{ taskType.count === 1 ? 'timer' : 'timers' }}
                        </span>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-2.5 text-sm">
                        <div>
                            <dt
                                class="text-[11px] tracking-wider text-muted-foreground uppercase"
                            >
                                Average
                            </dt>
                            <dd class="mt-0.5 font-mono text-lg tabular-nums">
                                {{ format(taskType.average) }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-[11px] tracking-wider text-muted-foreground uppercase"
                            >
                                Median
                            </dt>
                            <dd class="mt-0.5 font-mono text-lg tabular-nums">
                                {{ format(taskType.median) }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-[11px] tracking-wider text-muted-foreground uppercase"
                            >
                                Min
                            </dt>
                            <dd class="mt-0.5 font-mono text-lg tabular-nums">
                                {{ format(taskType.min) }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-[11px] tracking-wider text-muted-foreground uppercase"
                            >
                                Max
                            </dt>
                            <dd class="mt-0.5 font-mono text-lg tabular-nums">
                                {{ format(taskType.max) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
    </div>
</template>
