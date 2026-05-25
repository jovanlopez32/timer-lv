<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDate,
    type DateValue,
    getLocalTimeZone,
    parseDate,
    today,
} from '@internationalized/date';
import { CalendarIcon, Check, ChevronsUpDown, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
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
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type TaskType = { id: number; name: string };

type TimerRow = {
    id: number;
    agent: string;
    task_type: string;
    decimal_hours: number;
    ended_at: string | null;
};

type Filters = {
    task_type_id: number | null;
    from: string | null;
    to: string | null;
};

const props = defineProps<{
    taskTypes: TaskType[];
    timers: TimerRow[];
    filters: Filters;
    totalHours: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Reports', href: '/admin/reports' },
        ],
    },
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

const selectedTaskTypeId = ref<number | null>(props.filters.task_type_id);
const taskTypeOpen = ref(false);
const dateRange = ref<{ start: DateValue | undefined; end: DateValue | undefined }>({
    start: parseISODate(props.filters.from),
    end: parseISODate(props.filters.to),
});
const calendarOpen = ref(false);

const selectedTaskType = computed<TaskType | null>(
    () =>
        props.taskTypes.find((t) => t.id === selectedTaskTypeId.value) ?? null,
);

const selectTaskType = (taskType: TaskType | null) => {
    selectedTaskTypeId.value = taskType?.id ?? null;
    taskTypeOpen.value = false;
};

const formatDate = (iso: string | null) => {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString();
};

const formatRangeLabel = computed(() => {
    if (!dateRange.value.start && !dateRange.value.end) {
        return 'Pick a date range';
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

const search = () => {
    router.get(
        '/admin/reports',
        {
            task_type_id: selectedTaskTypeId.value ?? undefined,
            from: dateRange.value.start
                ? dateRange.value.start.toString()
                : undefined,
            to: dateRange.value.end
                ? dateRange.value.end.toString()
                : undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const reset = () => {
    selectedTaskTypeId.value = null;
    dateRange.value = { start: undefined, end: undefined };
    router.get(
        '/admin/reports',
        {},
        { preserveState: true, preserveScroll: true },
    );
};

const initialRange = today(getLocalTimeZone());
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Reports</h1>
            <p class="text-sm text-muted-foreground">
                Filter completed timers by task type and date range.
            </p>
        </header>

        <section
            class="rounded-xl border bg-card p-6 text-card-foreground shadow-sm"
        >
            <div class="grid gap-4 md:grid-cols-[1fr_1fr_auto_auto] md:items-end">
                <div class="grid gap-2">
                    <Label>Task type</Label>
                    <Popover v-model:open="taskTypeOpen">
                        <PopoverTrigger as-child>
                            <Button
                                variant="outline"
                                role="combobox"
                                :aria-expanded="taskTypeOpen"
                                class="justify-between"
                            >
                                <span class="truncate">
                                    {{
                                        selectedTaskType
                                            ? selectedTaskType.name
                                            : 'All task types'
                                    }}
                                </span>
                                <ChevronsUpDown
                                    class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent
                            class="w-[--reka-popover-trigger-width] p-0"
                        >
                            <Command>
                                <CommandInput
                                    placeholder="Search task type..."
                                />
                                <CommandList>
                                    <CommandEmpty>
                                        No task type found.
                                    </CommandEmpty>
                                    <CommandGroup>
                                        <CommandItem
                                            value="all"
                                            @select="selectTaskType(null)"
                                        >
                                            <Check
                                                :class="
                                                    cn(
                                                        'mr-2 h-4 w-4',
                                                        selectedTaskTypeId ===
                                                            null
                                                            ? 'opacity-100'
                                                            : 'opacity-0',
                                                    )
                                                "
                                            />
                                            All task types
                                        </CommandItem>
                                        <CommandItem
                                            v-for="taskType in taskTypes"
                                            :key="taskType.id"
                                            :value="taskType.name"
                                            @select="selectTaskType(taskType)"
                                        >
                                            <Check
                                                :class="
                                                    cn(
                                                        'mr-2 h-4 w-4',
                                                        selectedTaskTypeId ===
                                                            taskType.id
                                                            ? 'opacity-100'
                                                            : 'opacity-0',
                                                    )
                                                "
                                            />
                                            {{ taskType.name }}
                                        </CommandItem>
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>

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

        <section class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <div
                class="flex items-center justify-between border-b p-4 text-sm text-muted-foreground"
            >
                <span>
                    Showing
                    <span class="font-medium text-foreground">
                        {{ timers.length }}
                    </span>
                    {{ timers.length === 1 ? 'timer' : 'timers' }}
                </span>
                <span>
                    Total:
                    <span class="font-medium text-foreground tabular-nums">
                        {{ totalHours.toFixed(2) }} hours
                    </span>
                </span>
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
                    <TableEmpty v-if="timers.length === 0" :colspan="4">
                        No timers match the filters.
                    </TableEmpty>
                    <TableRow v-for="timer in timers" :key="timer.id">
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
        </section>
    </div>
</template>
