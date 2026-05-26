<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    ChevronsUpDown,
    Copy,
    CopyCheck,
    Pause,
    ParkingCircle,
    PictureInPicture2,
    Play,
    Square,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { usePictureInPictureWindow } from '@/composables/usePictureInPictureWindow';
import { cn } from '@/lib/utils';

type TaskType = { id: number; name: string };

type Agent = {
    id: number;
    name: string;
    slug: string;
    brand: string;
};

type ActiveTimer = {
    id: number;
    task_type_id: number;
    task_type: TaskType;
    started_at: string;
    elapsed_seconds: number;
    is_running: boolean;
};

type ParkedTimer = {
    id: number;
    task_type: TaskType;
    parked_at: string | null;
    elapsed_seconds: number;
};

const props = defineProps<{
    agent: Agent;
    taskTypes: TaskType[];
    activeTimer: ActiveTimer | null;
    parkedTimers: ParkedTimer[];
}>();

const page = usePage<{
    flash: {
        completedHours?: number;
        completedSeconds?: number;
        completedTimerId?: number;
    };
}>();

const selectedTaskTypeId = ref<number | null>(
    props.activeTimer?.task_type_id ?? null,
);
const comboboxOpen = ref(false);
const hasShownTimer = ref(!!props.activeTimer);
const completedHours = ref<number | null>(null);
const completedSeconds = ref<number | null>(null);
const completedModalOpen = ref(false);
const copied = ref(false);
const confirmCompleteOpen = ref(false);

watch(
    () => props.activeTimer,
    (timer) => {
        selectedTaskTypeId.value = timer?.task_type_id ?? null;
        if (timer) {
            hasShownTimer.value = true;
        }
    },
);

watch(
    () => page.props.flash?.completedTimerId,
    (timerId) => {
        if (typeof timerId !== 'number') {
            return;
        }
        const hours = page.props.flash?.completedHours;
        const seconds = page.props.flash?.completedSeconds;
        if (typeof hours !== 'number' || typeof seconds !== 'number') {
            return;
        }
        completedHours.value = hours;
        completedSeconds.value = seconds;
        completedModalOpen.value = true;
        copied.value = false;
    },
    { immediate: true },
);

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

const completedDuration = computed(() =>
    formatDuration(completedSeconds.value ?? 0),
);

const completedHoursText = computed(() =>
    completedHours.value !== null ? completedHours.value.toFixed(2) : '',
);

const copyHours = async () => {
    if (completedHours.value === null) {
        return;
    }
    try {
        await navigator.clipboard.writeText(completedHoursText.value);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
};

const selectedTaskType = computed<TaskType | null>(
    () =>
        props.taskTypes.find((t) => t.id === selectedTaskTypeId.value) ?? null,
);

const showTimer = computed(
    () => selectedTaskTypeId.value !== null || !!props.activeTimer,
);

const elapsedSeconds = ref(props.activeTimer?.elapsed_seconds ?? 0);
const isRunning = ref(props.activeTimer?.is_running ?? false);

// elapsed_seconds is a server-computed snapshot. snapshotAt records the client
// time when we received it, so the tick can extrapolate using only client clock
// deltas and stay correct across tab close/reopen and clock skew.
const elapsedSnapshot = ref(props.activeTimer?.elapsed_seconds ?? 0);
const snapshotAt = ref(Date.now());

let intervalId: number | null = null;

const recomputeElapsed = () => {
    elapsedSeconds.value = isRunning.value
        ? elapsedSnapshot.value +
          Math.floor((Date.now() - snapshotAt.value) / 1000)
        : elapsedSnapshot.value;
};

const stopTicking = () => {
    if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
    }
};

const startTicking = () => {
    stopTicking();
    intervalId = window.setInterval(recomputeElapsed, 250);
};

const onVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        recomputeElapsed();
    }
};

watch(
    () => props.activeTimer,
    (timer) => {
        elapsedSnapshot.value = timer?.elapsed_seconds ?? 0;
        snapshotAt.value = Date.now();
        isRunning.value = timer?.is_running ?? false;
        recomputeElapsed();
        if (isRunning.value) {
            startTicking();
        } else {
            stopTicking();
        }
    },
    { immediate: true },
);

if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', onVisibilityChange);
}

onBeforeUnmount(() => {
    stopTicking();
    if (typeof document !== 'undefined') {
        document.removeEventListener('visibilitychange', onVisibilityChange);
    }
});

const formattedElapsed = computed(() => formatDuration(elapsedSeconds.value));

const processing = ref(false);

const selectTaskType = (taskType: TaskType) => {
    selectedTaskTypeId.value = taskType.id;
    comboboxOpen.value = false;
};

const start = () => {
    if (!selectedTaskTypeId.value || processing.value) {
        return;
    }
    processing.value = true;
    router.post(
        `/${props.agent.slug}/timers`,
        { task_type_id: selectedTaskTypeId.value },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const pause = () => {
    if (!props.activeTimer || processing.value) {
        return;
    }
    processing.value = true;
    router.post(
        `/timers/${props.activeTimer.id}/pause`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const resume = () => {
    if (!props.activeTimer || processing.value) {
        return;
    }
    processing.value = true;
    router.post(
        `/timers/${props.activeTimer.id}/resume`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const park = () => {
    if (!props.activeTimer || isRunning.value || processing.value) {
        return;
    }
    processing.value = true;
    router.post(
        `/timers/${props.activeTimer.id}/park`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const unpark = (timer: ParkedTimer) => {
    if (processing.value || hasActiveTimer.value) {
        return;
    }
    processing.value = true;
    router.post(
        `/timers/${timer.id}/unpark`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};

const complete = () => {
    if (!props.activeTimer || processing.value) {
        return;
    }
    processing.value = true;
    router.post(
        `/timers/${props.activeTimer.id}/complete`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                confirmCompleteOpen.value = false;
            },
        },
    );
};

const onPrimaryClick = () => {
    if (!props.activeTimer) {
        start();
        return;
    }
    if (isRunning.value) {
        pause();
        return;
    }
    resume();
};

// Pull the user back to the main page before asking for confirmation so the
// AlertDialog doesn't open behind the PiP window. window.focus() raises the
// opener tab even if the browser is backgrounded.
const requestComplete = () => {
    if (!props.activeTimer || processing.value) {
        return;
    }
    if (pipOpen.value) {
        closePip();
        window.focus();
    }
    confirmCompleteOpen.value = true;
};

const hasActiveTimer = computed(() => !!props.activeTimer);
const canPark = computed(() => hasActiveTimer.value && !isRunning.value);

const {
    isSupported: pipSupported,
    isOpen: pipOpen,
    pipBody,
    open: openPip,
    close: closePip,
} = usePictureInPictureWindow();

const togglePip = () => {
    if (pipOpen.value) {
        closePip();
    } else {
        openPip();
    }
};

// Same reasoning as requestComplete: if a completion summary arrives while
// PiP is open, bring the user back so they see the summary.
watch(completedModalOpen, (open) => {
    if (open && pipOpen.value) {
        closePip();
        window.focus();
    }
});

const timerSectionClass = computed(() =>
    pipOpen.value
        ? 'flex min-h-screen flex-col justify-center gap-6 bg-background p-6'
        : 'space-y-6 rounded-2xl border bg-card p-8 text-card-foreground shadow-sm',
);
</script>

<template>
    <Head :title="agent.name" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-background px-6 py-12 text-foreground"
    >
        <div
            class="grid w-full max-w-5xl gap-8 lg:grid-cols-[minmax(0,1fr)_280px]"
        >
            <div class="mx-auto w-full max-w-md space-y-8">
                <header class="space-y-1 text-center">
                    <h1 class="text-3xl font-semibold tracking-tight">
                        {{ agent.name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ agent.brand }}
                    </p>
                </header>

                <section class="space-y-2">
                    <p class="mb-1.5 text-sm font-medium text-foreground">
                        Task type
                    </p>
                    <Popover v-model:open="comboboxOpen">
                        <PopoverTrigger as-child>
                            <Button
                                variant="outline"
                                role="combobox"
                                :aria-expanded="comboboxOpen"
                                :disabled="hasActiveTimer"
                                class="w-full justify-between"
                            >
                                <span class="truncate">
                                    {{
                                        selectedTaskType
                                            ? selectedTaskType.name
                                            : 'Select a task type...'
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
                </section>

                <div
                    v-if="showTimer && pipOpen"
                    class="space-y-3 rounded-2xl border border-dashed bg-card/50 p-6 text-center text-card-foreground"
                >
                    <p class="text-sm text-muted-foreground">
                        Timer running in floating window.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="closePip"
                    >
                        Bring back
                    </Button>
                </div>

                <Teleport :to="pipBody" :disabled="!pipBody">
                    <Transition
                        enter-active-class="transition-opacity duration-500"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="duration-0"
                        :appear="!hasShownTimer"
                    >
                        <section v-if="showTimer" :class="timerSectionClass">
                            <div class="relative">
                                <Button
                                    v-if="pipSupported"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="absolute right-0 top-0 h-8 w-8 text-muted-foreground"
                                    @click="togglePip"
                                >
                                    <X v-if="pipOpen" class="hidden h-4 w-4" />
                                    <PictureInPicture2
                                        v-else
                                        class="h-4 w-4"
                                    />
                                    <span class="sr-only">
                                        {{
                                            pipOpen
                                                ? 'Close floating window'
                                                : 'Open in floating window'
                                        }}
                                    </span>
                                </Button>
                                <p
                                    class="text-center font-mono text-4xl font-light tracking-widest tabular-nums"
                                >
                                    {{ formattedElapsed }}
                                </p>
                                <p
                                    class="mt-2 text-center text-xs uppercase tracking-wider text-muted-foreground"
                                >
                                    {{
                                        hasActiveTimer
                                            ? isRunning
                                                ? 'Running'
                                                : 'Paused'
                                            : 'Ready to start'
                                    }}
                                </p>
                            </div>

                            <div class="flex items-center justify-center gap-3">
                                <Button
                                    type="button"
                                    size="lg"
                                    :disabled="
                                        processing ||
                                        (!hasActiveTimer && !selectedTaskTypeId)
                                    "
                                    class="h-14 w-14 rounded-full p-0"
                                    @click="onPrimaryClick"
                                >
                                    <Play
                                        v-if="!isRunning"
                                        class="h-6 w-6 fill-current"
                                    />
                                    <Pause
                                        v-else
                                        class="h-6 w-6 fill-current"
                                    />
                                    <span class="sr-only">
                                        {{ isRunning ? 'Pause' : 'Play' }}
                                    </span>
                                </Button>

                                <Button
                                    v-if="hasActiveTimer"
                                    type="button"
                                    variant="secondary"
                                    size="lg"
                                    :disabled="processing"
                                    @click="requestComplete"
                                >
                                    <Square class="mr-2 h-4 w-4 fill-current" />
                                    Mark as completed
                                </Button>
                            </div>

                            <div
                                v-if="hasActiveTimer"
                                class="flex justify-center"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing || !canPark"
                                    :title="
                                        canPark
                                            ? 'Park this task'
                                            : 'Pause the timer before parking'
                                    "
                                    @click="park"
                                >
                                    <ParkingCircle class="mr-2 h-4 w-4" />
                                    Park task
                                </Button>
                            </div>
                        </section>
                    </Transition>
                </Teleport>
            </div>

            <aside
                class="space-y-3 border-t pt-6 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0"
            >
                <div class="flex items-center justify-between">
                    <h2
                        class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                    >
                        Parked tasks
                    </h2>
                    <span class="text-xs text-muted-foreground">
                        {{ parkedTimers.length }}
                    </span>
                </div>

                <p
                    v-if="parkedTimers.length === 0"
                    class="rounded-lg border border-dashed bg-muted/30 px-3 py-6 text-center text-xs text-muted-foreground"
                >
                    No parked tasks. Pause a timer and park it to stash it
                    here.
                </p>

                <ul v-else class="space-y-3">
                    <li
                        v-for="timer in parkedTimers"
                        :key="timer.id"
                        class="space-y-2 rounded-2xl border bg-card p-4 text-card-foreground shadow-sm"
                    >
                        <p
                            class="truncate text-sm font-medium"
                            :title="timer.task_type.name"
                        >
                            {{ timer.task_type.name }}
                        </p>
                        <p
                            class="font-mono text-lg font-light tabular-nums text-muted-foreground"
                        >
                            {{ formatDuration(timer.elapsed_seconds) }}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="w-full"
                            :disabled="processing || hasActiveTimer"
                            :title="
                                hasActiveTimer
                                    ? 'Park or complete the active task first'
                                    : 'Resume this task'
                            "
                            @click="unpark(timer)"
                        >
                            <Play class="mr-2 h-3.5 w-3.5 fill-current" />
                            Resume
                        </Button>
                    </li>
                </ul>
            </aside>
        </div>

        <AlertDialog v-model:open="confirmCompleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Mark task as completed?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will stop the timer and record the final time.
                        You can't undo this from here.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border bg-muted/40 px-3 py-3">
                        <p
                            class="text-xs uppercase tracking-wider text-muted-foreground"
                        >
                            Task
                        </p>
                        <p class="mt-1 truncate text-sm font-medium">
                            {{ activeTimer?.task_type.name ?? '—' }}
                        </p>
                    </div>
                    <div class="rounded-lg border bg-muted/40 px-3 py-3">
                        <p
                            class="text-xs uppercase tracking-wider text-muted-foreground"
                        >
                            Elapsed
                        </p>
                        <p
                            class="mt-1 font-mono text-sm font-medium tabular-nums"
                        >
                            {{ formattedElapsed }}
                        </p>
                    </div>
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel :disabled="processing">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        :disabled="processing"
                        @click="complete"
                    >
                        Complete task
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <Dialog v-model:open="completedModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Timer completed</DialogTitle>
                    <DialogDescription>
                        Total time tracked for this task.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3">
                    <div
                        class="flex flex-col items-center rounded-lg border bg-muted/50 py-6"
                    >
                        <span
                            class="text-xs uppercase tracking-wider text-muted-foreground"
                        >
                            Total time
                        </span>
                        <span
                            class="mt-1 font-mono text-3xl font-light tracking-widest tabular-nums"
                        >
                            {{ completedDuration }}
                        </span>
                    </div>
                    <div
                        class="flex flex-col items-center rounded-lg border bg-muted/50 py-6"
                    >
                        <span
                            class="text-xs uppercase tracking-wider text-muted-foreground"
                        >
                            Decimal hours
                        </span>
                        <span
                            class="mt-1 font-mono text-3xl font-light tracking-tight tabular-nums"
                        >
                            {{ completedHoursText }}
                        </span>
                    </div>
                </div>

                <DialogFooter class="sm:justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        @click="completedModalOpen = false"
                    >
                        Close
                    </Button>
                    <Button type="button" @click="copyHours">
                        <CopyCheck v-if="copied" class="mr-2 h-4 w-4" />
                        <Copy v-else class="mr-2 h-4 w-4" />
                        {{ copied ? 'Copied!' : 'Copy hours' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
