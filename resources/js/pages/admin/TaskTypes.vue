<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

type Brand = { id: number; name: string };

type TaskType = {
    id: number;
    name: string;
    brand_id: number;
    brand: string | null;
    created_at: string;
};

const props = defineProps<{ taskTypes: TaskType[]; brands: Brand[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Settings', href: '/admin/task-types' },
            { title: 'Task types', href: '/admin/task-types' },
        ],
    },
});

const groups = computed(() =>
    props.brands.map((brand) => ({
        brand,
        taskTypes: props.taskTypes.filter((t) => t.brand_id === brand.id),
    })),
);

// Only one row is ever active at a time: renaming or confirming a delete.
// Keeping these mutually exclusive avoids stacked inline forms competing
// for attention inside the same brand section.
const editingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);

const deletingTaskType = computed(() =>
    props.taskTypes.find((t) => t.id === deletingId.value) ?? null,
);

const nameInputs = ref<Record<string, HTMLInputElement | null>>({});
const setNameInputRef = (key: string) => (el: unknown) => {
    nameInputs.value[key] = el as HTMLInputElement | null;
};
const focusInput = (key: string) => {
    nextTick(() => nameInputs.value[key]?.focus());
};

const startEditing = (taskType: TaskType) => {
    deletingId.value = null;
    editingId.value = taskType.id;
    focusInput(`edit-${taskType.id}`);
};

const createOpen = ref(false);
const createBrandId = ref<string | undefined>(
    props.brands.length > 0 ? String(props.brands[0].id) : undefined,
);

const openCreate = () => {
    createBrandId.value =
        props.brands.length > 0 ? String(props.brands[0].id) : undefined;
    createOpen.value = true;
};

const confirmDestroy = (taskType: TaskType) => {
    deletingId.value = null;
    router.delete(`/admin/task-types/${taskType.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Task types" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <header class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Task types</h1>
                <p class="text-sm text-muted-foreground">
                    Categories that agents of a project can pick when
                    starting a timer.
                </p>
            </div>
            <Button v-if="brands.length > 0" @click="openCreate">
                <Plus class="h-4 w-4" />
                Add task type
            </Button>
        </header>

        <section
            v-if="brands.length === 0"
            class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground"
        >
            You need to
            <Link
                href="/admin/brands"
                class="font-medium text-primary hover:underline"
            >
                create a project
            </Link>
            before adding task types.
        </section>

        <section v-else class="space-y-4">
            <div
                v-for="group in groups"
                :key="group.brand.id"
                class="rounded-lg border text-card-foreground"
            >
                <div class="flex items-center gap-2 border-b px-5 py-3">
                    <h2 class="text-base font-semibold">
                        {{ group.brand.name }}
                    </h2>
                    <Badge variant="secondary">{{
                        group.taskTypes.length
                    }}</Badge>
                </div>

                <ul class="divide-y">
                    <li
                        v-if="group.taskTypes.length === 0"
                        class="px-5 py-6 text-sm text-muted-foreground"
                    >
                        No task types yet. Agents of {{ group.brand.name }}
                        won't see any category until you add one.
                    </li>

                    <li
                        v-for="taskType in group.taskTypes"
                        :key="taskType.id"
                        class="px-5 py-2.5"
                    >
                        <Form
                            v-if="editingId === taskType.id"
                            :action="`/admin/task-types/${taskType.id}`"
                            method="patch"
                            v-slot="{ errors, processing }"
                            @success="editingId = null"
                            class="flex items-start gap-2"
                        >
                            <input
                                type="hidden"
                                name="brand_id"
                                :value="taskType.brand_id"
                            />
                            <div class="flex-1">
                                <Input
                                    :ref="setNameInputRef(`edit-${taskType.id}`)"
                                    name="name"
                                    :default-value="taskType.name"
                                    required
                                    autocomplete="off"
                                    @keydown.escape="editingId = null"
                                />
                                <InputError :message="errors.name" />
                            </div>
                            <Button
                                type="submit"
                                size="icon"
                                variant="ghost"
                                :disabled="processing"
                                title="Save"
                            >
                                <Spinner v-if="processing" />
                                <Check v-else class="h-4 w-4" />
                                <span class="sr-only">Save</span>
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                title="Cancel"
                                @click="editingId = null"
                            >
                                <X class="h-4 w-4" />
                                <span class="sr-only">Cancel</span>
                            </Button>
                        </Form>

                        <div
                            v-else
                            class="flex h-9 items-center justify-between gap-4"
                        >
                            <span class="text-sm font-medium">{{
                                taskType.name
                            }}</span>
                            <div class="flex gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    title="Rename"
                                    @click="startEditing(taskType)"
                                >
                                    <Pencil class="h-4 w-4" />
                                    <span class="sr-only">Rename</span>
                                </Button>
                                <Popover
                                    :open="deletingId === taskType.id"
                                    @update:open="
                                        (open: boolean) =>
                                            (deletingId = open
                                                ? taskType.id
                                                : null)
                                    "
                                >
                                    <PopoverTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="text-destructive hover:text-destructive"
                                            title="Delete"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            <span class="sr-only"
                                                >Delete</span
                                            >
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent
                                        align="end"
                                        class="w-72 space-y-3"
                                    >
                                        <p class="text-sm">
                                            Delete
                                            <span class="font-medium"
                                                >"{{
                                                    deletingTaskType?.name
                                                }}"</span
                                            >? This can't be undone.
                                        </p>
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="deletingId = null"
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                                @click="
                                                    confirmDestroy(taskType)
                                                "
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <Dialog v-model:open="createOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Add task type</DialogTitle>
                </DialogHeader>
                <Form
                    action="/admin/task-types"
                    method="post"
                    :reset-on-success="true"
                    v-slot="{ errors, processing }"
                    @success="createOpen = false"
                    class="grid gap-4"
                >
                    <div class="grid gap-2">
                        <Label for="create-brand_id">Project</Label>
                        <Select
                            v-model="createBrandId"
                            name="brand_id"
                            required
                        >
                            <SelectTrigger id="create-brand_id">
                                <SelectValue placeholder="Select a project" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="brand in brands"
                                    :key="brand.id"
                                    :value="String(brand.id)"
                                >
                                    {{ brand.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.brand_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-name">Name</Label>
                        <Input
                            id="create-name"
                            name="name"
                            placeholder="e.g. Design review"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="createOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <Spinner v-if="processing" />
                            Create task type
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
