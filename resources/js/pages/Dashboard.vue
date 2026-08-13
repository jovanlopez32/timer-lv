<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import type { CalendarDate } from '@internationalized/date';
import { CalendarIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';

type Developer = {
    id: number;
    name: string;
    tasks_count: number;
    hours: number;
};

type TaskTypeStat = {
    id: number;
    name: string;
    brand: string | null;
    tasks_count: number;
    hours: number;
};

type BrandStat = {
    id: number;
    name: string;
    color: string;
    tasks_count: number;
    hours: number;
};

type Range = 'this_week' | 'last_week' | 'custom';

const props = defineProps<{
    period: { range: Range; from: string; to: string };
    month: { name: string; year: number; sitesCount: number };
    totals: {
        tasksCount: number;
        hoursSum: number;
        avgHours: number | null;
    };
    developers: Developer[];
    topTaskTypes: TaskTypeStat[];
    topBrands: BrandStat[];
    live: { active: number; parked: number };
    overview: { agentsCount: number; brandsCount: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const selectedRange = ref<Range>(props.period.range);
const calendarOpen = ref(false);
const initialCalendarDate = today(getLocalTimeZone());

const parseISODate = (value: string): CalendarDate | undefined => {
    try {
        return parseDate(value);
    } catch {
        return undefined;
    }
};

const dateRange = ref<any>({
    start: parseISODate(props.period.from),
    end: parseISODate(props.period.to),
});

const rangeLabels: Record<Range, string> = {
    this_week: 'This week',
    last_week: 'Last week',
    custom: 'Custom range',
};

const periodLabel = computed(() => {
    if (selectedRange.value !== 'custom') {
        return rangeLabels[selectedRange.value];
    }

    const tz = getLocalTimeZone();
    const start = dateRange.value.start?.toDate(tz).toLocaleDateString();
    const end = dateRange.value.end?.toDate(tz).toLocaleDateString();

    return start && end ? `${start} → ${end}` : 'Pick a range';
});

const navigate = (params: Record<string, string>) => {
    router.get('/dashboard', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

watch(selectedRange, (value) => {
    if (value === 'custom') {
        calendarOpen.value = true;

        return;
    }

    navigate({ range: value });
});

const applyCustomRange = () => {
    if (!dateRange.value.start || !dateRange.value.end) {
        return;
    }

    calendarOpen.value = false;
    navigate({
        range: 'custom',
        from: dateRange.value.start.toString(),
        to: dateRange.value.end.toString(),
    });
};

const formatHours = (value: number) => `${value.toFixed(1)}h`;

const maxDeveloperTasks = computed(() =>
    Math.max(1, ...props.developers.map((d) => d.tasks_count)),
);

const maxBrandHours = computed(() =>
    Math.max(1, ...props.topBrands.map((b) => b.hours)),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-8 p-6">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    Overview of work completed by the team.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Select v-model="selectedRange">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue :placeholder="periodLabel" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="this_week">This week</SelectItem>
                        <SelectItem value="last_week">Last week</SelectItem>
                        <SelectItem value="custom">Custom range</SelectItem>
                    </SelectContent>
                </Select>

                <Popover v-model:open="calendarOpen">
                    <PopoverTrigger as-child>
                        <Button
                            v-if="selectedRange === 'custom'"
                            variant="outline"
                            class="justify-start font-normal"
                        >
                            <CalendarIcon class="mr-2 h-4 w-4" />
                            {{ periodLabel }}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto p-0" align="end">
                        <RangeCalendar
                            v-model="dateRange"
                            :placeholder="initialCalendarDate"
                            :number-of-months="2"
                            weekday-format="short"
                        />
                        <div class="flex justify-end gap-2 border-t p-3">
                            <Button size="sm" @click="applyCustomRange">
                                Apply
                            </Button>
                        </div>
                    </PopoverContent>
                </Popover>
            </div>
        </header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Month hero -->
            <div
                class="col-span-1 row-span-2 flex flex-col justify-between rounded-2xl bg-muted/40 p-6 sm:col-span-2"
            >
                <div>
                    <p
                        class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                    >
                        {{ month.year }}
                    </p>
                    <h2
                        class="mt-1 text-5xl font-bold tracking-tight lg:text-6xl"
                    >
                        {{ month.name }}
                    </h2>
                </div>
                <div class="mt-6 flex items-end justify-between">
                    <div>
                        <p class="text-4xl font-semibold tabular-nums">
                            {{ month.sitesCount }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                month.sitesCount === 1
                                    ? 'site worked on this month'
                                    : 'sites worked on this month'
                            }}
                        </p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ overview.brandsCount }} total brands
                    </p>
                </div>
            </div>

            <!-- Live now -->
            <div class="col-span-1 rounded-2xl bg-muted/40 p-5">
                <p
                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    Live now
                </p>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-semibold tabular-nums">{{
                        live.active
                    }}</span>
                    <span class="text-sm text-muted-foreground">active</span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ live.parked }} parked
                </p>
            </div>

            <!-- Avg hours -->
            <div class="col-span-1 rounded-2xl bg-muted/40 p-5">
                <p
                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    Avg time / task
                </p>
                <p class="mt-2 text-3xl font-semibold tabular-nums">
                    {{
                        totals.avgHours === null
                            ? '—'
                            : formatHours(totals.avgHours)
                    }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ periodLabel }}
                </p>
            </div>

            <!-- Total tasks -->
            <div class="col-span-1 rounded-2xl bg-muted/40 p-5">
                <p
                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    Completed tasks
                </p>
                <p class="mt-2 text-3xl font-semibold tabular-nums">
                    {{ totals.tasksCount }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ periodLabel }}
                </p>
            </div>

            <!-- Total hours -->
            <div class="col-span-1 rounded-2xl bg-muted/40 p-5">
                <p
                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    Hours logged
                </p>
                <p class="mt-2 text-3xl font-semibold tabular-nums">
                    {{ formatHours(totals.hoursSum) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ periodLabel }}
                </p>
            </div>

            <!-- Developers leaderboard -->
            <div
                class="col-span-1 row-span-2 rounded-2xl bg-muted/40 p-6 sm:col-span-2"
            >
                <div class="mb-4 flex items-baseline justify-between">
                    <h3 class="text-lg font-semibold">Developers</h3>
                    <span
                        class="text-xs tracking-wider text-muted-foreground uppercase"
                    >
                        {{ periodLabel }}
                    </span>
                </div>

                <p
                    v-if="developers.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No completed tasks in this period.
                </p>

                <ul v-else class="space-y-3">
                    <li
                        v-for="(developer, index) in developers"
                        :key="developer.id"
                        class="flex items-center gap-3"
                    >
                        <span
                            class="w-5 shrink-0 text-sm font-medium text-muted-foreground tabular-nums"
                        >
                            {{ index + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-baseline justify-between gap-2"
                            >
                                <span class="truncate text-sm font-medium">{{
                                    developer.name
                                }}</span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ developer.tasks_count }}
                                    {{
                                        developer.tasks_count === 1
                                            ? 'task'
                                            : 'tasks'
                                    }}
                                    · {{ formatHours(developer.hours) }}
                                </span>
                            </div>
                            <div
                                class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-background"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${(developer.tasks_count / maxDeveloperTasks) * 100}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Top task types -->
            <div
                class="col-span-1 rounded-2xl bg-muted/40 p-6 sm:col-span-2"
            >
                <h3 class="mb-4 text-lg font-semibold">Top task types</h3>
                <p
                    v-if="topTaskTypes.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No completed tasks in this period.
                </p>
                <ul v-else class="space-y-2.5">
                    <li
                        v-for="taskType in topTaskTypes"
                        :key="taskType.id"
                        class="flex items-center justify-between gap-2 text-sm"
                    >
                        <span class="min-w-0 truncate">
                            {{ taskType.name }}
                            <span
                                v-if="taskType.brand"
                                class="text-muted-foreground"
                            >
                                · {{ taskType.brand }}
                            </span>
                        </span>
                        <span
                            class="shrink-0 text-muted-foreground tabular-nums"
                        >
                            {{ taskType.tasks_count }}
                            {{ taskType.tasks_count === 1 ? 'task' : 'tasks' }}
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Top brands -->
            <div
                class="col-span-1 rounded-2xl bg-muted/40 p-6 sm:col-span-2"
            >
                <h3 class="mb-4 text-lg font-semibold">Top sites by hours</h3>
                <p
                    v-if="topBrands.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No completed tasks in this period.
                </p>
                <ul v-else class="space-y-3">
                    <li
                        v-for="brand in topBrands"
                        :key="brand.id"
                        class="flex items-center gap-3"
                    >
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: brand.color }"
                        />
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-baseline justify-between gap-2"
                            >
                                <span class="truncate text-sm font-medium">{{
                                    brand.name
                                }}</span>
                                <span
                                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ formatHours(brand.hours) }}
                                </span>
                            </div>
                            <div
                                class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-background"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :style="{
                                        width: `${(brand.hours / maxBrandHours) * 100}%`,
                                        backgroundColor: brand.color,
                                    }"
                                />
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
