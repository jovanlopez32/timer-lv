<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDate,
    getLocalTimeZone,
    parseDate,
    today,
} from '@internationalized/date';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CalendarIcon,
    Check,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    Download,
    Pencil,
    Search,
    Trash2,
    TriangleAlert,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
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
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

type TaskType = { id: number; name: string; brand: string | null };

type TimerRow = {
    id: number;
    agent: string;
    brand: string | null;
    task_type_id: number;
    task_type: string;
    decimal_hours: number;
    started_at: string | null;
    ended_at: string | null;
};

type Sort = 'recent' | 'hours_asc' | 'hours_desc';

type Filters = {
    task_type_id: number | null;
    from: string | null;
    to: string | null;
    sort: Sort;
};

const props = defineProps<{
    taskTypes: TaskType[];
    timers: {
        data: TimerRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
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
const dateRange = ref<any>({
    start: parseISODate(props.filters.from),
    end: parseISODate(props.filters.to),
});
const calendarOpen = ref(false);
const sort = ref<Sort>(props.filters.sort ?? 'recent');
const editing = ref<TimerRow | null>(null);
const deleting = ref<TimerRow | null>(null);
const editForm = ref({
    task_type_id: '',
    decimal_hours: '',
    started_at: '',
    ended_at: '',
});

const selectedTaskType = computed<TaskType | null>(
    () =>
        props.taskTypes.find((t) => t.id === selectedTaskTypeId.value) ?? null,
);

// Task type names repeat across brands, so every picker groups and labels them
// by brand to keep the duplicates distinguishable.
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

const taskTypeLabel = (taskType: TaskType) =>
    `${taskType.brand ?? 'No project'} — ${taskType.name}`;

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

const toDateTimeLocal = (iso: string | null) => {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
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

const queryParams = computed(() => ({
    task_type_id: selectedTaskTypeId.value ?? undefined,
    from: dateRange.value.start ? dateRange.value.start.toString() : undefined,
    to: dateRange.value.end ? dateRange.value.end.toString() : undefined,
    sort: sort.value === 'recent' ? undefined : sort.value,
}));

const visit = (extra: Record<string, unknown> = {}) => {
    router.get(
        '/admin/reports',
        { ...queryParams.value, ...extra },
        { preserveState: true, preserveScroll: true },
    );
};

const search = () => {
    visit();
};

const reset = () => {
    selectedTaskTypeId.value = null;
    dateRange.value = { start: undefined, end: undefined };
    sort.value = 'recent';
    router.get(
        '/admin/reports',
        {},
        { preserveState: true, preserveScroll: true },
    );
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();

    Object.entries(queryParams.value).forEach(([key, value]) => {
        if (value !== undefined) {
            params.set(key, String(value));
        }
    });

    const query = params.toString();

    return query ? `/admin/reports/export?${query}` : '/admin/reports/export';
});

const changePage = (page: number) => {
    visit({ page });
};

// Cycles longest → shortest → back to most recent, so the header click both
// sorts and clears without a separate control.
const toggleHoursSort = () => {
    sort.value =
        sort.value === 'hours_desc'
            ? 'hours_asc'
            : sort.value === 'hours_asc'
              ? 'recent'
              : 'hours_desc';
    visit();
};

const hoursSortIcon = computed(() => {
    if (sort.value === 'hours_desc') {
        return ArrowDown;
    }

    if (sort.value === 'hours_asc') {
        return ArrowUp;
    }

    return ArrowUpDown;
});

const hoursSortLabel = computed(() => {
    if (sort.value === 'hours_desc') {
        return 'Sorted by longest first. Click to sort shortest first.';
    }

    if (sort.value === 'hours_asc') {
        return 'Sorted by shortest first. Click to clear sorting.';
    }

    return 'Click to sort by longest first.';
});

const openEdit = (timer: TimerRow) => {
    editing.value = timer;
    editForm.value = {
        task_type_id: String(timer.task_type_id),
        decimal_hours: timer.decimal_hours.toFixed(2),
        started_at: toDateTimeLocal(timer.started_at),
        ended_at: toDateTimeLocal(timer.ended_at),
    };
};

const saveEdit = () => {
    if (!editing.value) {
        return;
    }

    router.patch(
        `/admin/reports/${editing.value.id}`,
        {
            task_type_id: editForm.value.task_type_id,
            decimal_hours: editForm.value.decimal_hours,
            started_at: editForm.value.started_at,
            ended_at: editForm.value.ended_at,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = null;
            },
        },
    );
};

const destroy = (timer: TimerRow) => {
    deleting.value = timer;
};

const confirmDestroy = () => {
    if (!deleting.value) {
        return;
    }

    const timer = deleting.value;
    deleting.value = null;
    router.delete(`/admin/reports/${timer.id}`, { preserveScroll: true });
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

        <section class="text-card-foreground">
            <div
                class="grid gap-4 md:grid-cols-[1fr_1fr_auto_auto] md:items-end"
            >
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
                                            ? taskTypeLabel(selectedTaskType)
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
                                    </CommandGroup>
                                    <CommandGroup
                                        v-for="group in taskTypesByBrand"
                                        :key="group.brand"
                                        :heading="group.brand"
                                    >
                                        <CommandItem
                                            v-for="taskType in group.taskTypes"
                                            :key="taskType.id"
                                            :value="`${group.brand} ${taskType.name}`"
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

        <section class="overflow-x-auto rounded-lg border text-card-foreground">
            <div
                class="flex items-center justify-between gap-4 border-b px-5 py-4 text-sm text-muted-foreground"
            >
                <span>
                    Showing
                    <span class="font-medium text-foreground">
                        {{ timers.from ?? 0 }}-{{ timers.to ?? 0 }}
                    </span>
                    of
                    <span class="font-medium text-foreground">
                        {{ timers.total }}
                    </span>
                    timers
                </span>
                <div class="flex items-center gap-4">
                    <span>
                        Total:
                        <span class="font-medium text-foreground tabular-nums">
                            {{ totalHours.toFixed(2) }} hours
                        </span>
                    </span>
                    <Button as-child variant="outline" size="sm">
                        <a :href="exportUrl">
                            <Download class="mr-2 h-4 w-4" />
                            Export to Excel
                        </a>
                    </Button>
                </div>
            </div>
            <Table class="min-w-[1180px]">
                <TableHeader>
                    <TableRow>
                        <TableHead class="px-5">Agent</TableHead>
                        <TableHead class="px-5">Brand</TableHead>
                        <TableHead class="px-5">Task type</TableHead>
                        <TableHead class="px-5 text-right">
                            <button
                                type="button"
                                class="ml-auto flex items-center gap-1.5 rounded-md px-1 py-0.5 transition-colors hover:text-foreground"
                                :class="
                                    sort === 'recent'
                                        ? 'text-muted-foreground'
                                        : 'text-foreground'
                                "
                                :title="hoursSortLabel"
                                @click="toggleHoursSort"
                            >
                                Hours
                                <component
                                    :is="hoursSortIcon"
                                    class="h-3.5 w-3.5"
                                />
                            </button>
                        </TableHead>
                        <TableHead class="px-5">Started at</TableHead>
                        <TableHead class="px-5">Ended at</TableHead>
                        <TableHead class="w-48 px-5 text-right"
                            >Actions</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="timers.data.length === 0" :colspan="7">
                        No timers match the filters.
                    </TableEmpty>
                    <TableRow v-for="timer in timers.data" :key="timer.id">
                        <TableCell class="px-5 py-4 font-medium">
                            {{ timer.agent }}
                        </TableCell>
                        <TableCell class="px-5 py-4">{{
                            timer.brand ?? '—'
                        }}</TableCell>
                        <TableCell class="px-5 py-4">{{
                            timer.task_type
                        }}</TableCell>
                        <TableCell class="px-5 py-4 text-right tabular-nums">
                            {{ timer.decimal_hours.toFixed(2) }}
                        </TableCell>
                        <TableCell class="px-5 py-4">
                            {{ formatDate(timer.started_at) }}
                        </TableCell>
                        <TableCell class="px-5 py-4">
                            {{ formatDate(timer.ended_at) }}
                        </TableCell>
                        <TableCell class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="default"
                                    size="sm"
                                    @click="openEdit(timer)"
                                >
                                    <Pencil class="mr-2 h-4 w-4" />
                                    Edit
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    @click="destroy(timer)"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <div class="flex items-center justify-between border-t px-5 py-4">
                <div class="text-sm text-muted-foreground">
                    Page {{ timers.current_page }} of {{ timers.last_page }}
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="timers.current_page <= 1"
                        @click="changePage(timers.current_page - 1)"
                    >
                        <ChevronLeft class="mr-2 h-4 w-4" />
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="timers.current_page >= timers.last_page"
                        @click="changePage(timers.current_page + 1)"
                    >
                        Next
                        <ChevronRight class="ml-2 h-4 w-4" />
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
                    <DialogTitle>Edit report timer</DialogTitle>
                </DialogHeader>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="edit-task-type">Task type</Label>
                        <Select
                            v-model="editForm.task_type_id"
                            name="task_type_id"
                        >
                            <SelectTrigger id="edit-task-type">
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
                        <Label for="edit-hours">Hours</Label>
                        <Input
                            id="edit-hours"
                            v-model="editForm.decimal_hours"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit-started-at">Started at</Label>
                            <Input
                                id="edit-started-at"
                                v-model="editForm.started_at"
                                type="datetime-local"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit-ended-at">Ended at</Label>
                            <Input
                                id="edit-ended-at"
                                v-model="editForm.ended_at"
                                type="datetime-local"
                            />
                        </div>
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

        <Dialog
            :open="deleting !== null"
            @update:open="(open) => !open && (deleting = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete report timer</DialogTitle>
                </DialogHeader>
                <Alert variant="destructive">
                    <TriangleAlert class="h-4 w-4" />
                    <AlertTitle>Confirm deletion</AlertTitle>
                    <AlertDescription>
                        This will delete the completed timer for "{{
                            deleting?.agent
                        }}". This action cannot be undone.
                    </AlertDescription>
                </Alert>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="deleting = null"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="confirmDestroy"
                    >
                        Delete timer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
