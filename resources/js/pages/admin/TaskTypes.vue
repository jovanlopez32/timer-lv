<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2, TriangleAlert } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

const editing = ref<TaskType | null>(null);
const deleting = ref<TaskType | null>(null);
const newBrandId = ref<string | undefined>(
    props.brands.length > 0 ? String(props.brands[0].id) : undefined,
);
const editBrandId = ref<string | undefined>(undefined);

const openEdit = (taskType: TaskType) => {
    editing.value = { ...taskType };
    editBrandId.value = String(taskType.brand_id);
};

const destroy = (taskType: TaskType) => {
    deleting.value = taskType;
};

const confirmDestroy = () => {
    if (!deleting.value) {
        return;
    }

    const taskType = deleting.value;
    deleting.value = null;
    router.delete(`/admin/task-types/${taskType.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Task types" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Task types</h1>
            <p class="text-sm text-muted-foreground">
                Categories that agents of a brand can pick when starting a
                timer.
            </p>
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
                create a brand
            </Link>
            before adding task types.
        </section>

        <section v-else class="space-y-4">
            <h2 class="text-lg font-semibold">New task type</h2>
            <Form
                action="/admin/task-types"
                method="post"
                :reset-on-success="true"
                v-slot="{ errors, processing }"
                class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end"
            >
                <div class="grid gap-2">
                    <Label for="brand_id">Brand</Label>
                    <Select v-model="newBrandId" name="brand_id" required>
                        <SelectTrigger id="brand_id">
                            <SelectValue placeholder="Select a brand" />
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
                    <Label for="name">Name</Label>
                    <Input id="name" name="name" required autocomplete="off" />
                    <InputError :message="errors.name" />
                </div>
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Create task type
                </Button>
            </Form>
        </section>

        <section class="overflow-x-auto rounded-lg border text-card-foreground">
            <Table class="min-w-[640px]">
                <TableHeader>
                    <TableRow>
                        <TableHead class="px-5">Brand</TableHead>
                        <TableHead class="px-5">Name</TableHead>
                        <TableHead class="w-48 px-5 text-right"
                            >Actions</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="taskTypes.length === 0" :colspan="3">
                        No task types yet.
                    </TableEmpty>
                    <TableRow v-for="taskType in taskTypes" :key="taskType.id">
                        <TableCell class="px-5 py-4">{{
                            taskType.brand ?? '—'
                        }}</TableCell>
                        <TableCell class="px-5 py-4 font-medium">
                            {{ taskType.name }}
                        </TableCell>
                        <TableCell class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    variant="default"
                                    size="sm"
                                    @click="openEdit(taskType)"
                                    title="Edit"
                                >
                                    <Pencil class="mr-2 h-4 w-4" />
                                    Edit
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="destroy(taskType)"
                                    title="Delete"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <Dialog
            :open="editing !== null"
            @update:open="(open) => !open && (editing = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit task type</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="editing"
                    :action="`/admin/task-types/${editing.id}`"
                    method="patch"
                    v-slot="{ errors, processing }"
                    @success="editing = null"
                    class="grid gap-4"
                >
                    <div class="grid gap-2">
                        <Label for="edit-brand_id">Brand</Label>
                        <Select v-model="editBrandId" name="brand_id" required>
                            <SelectTrigger id="edit-brand_id">
                                <SelectValue placeholder="Select a brand" />
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
                        <Label for="edit-name">Name</Label>
                        <Input
                            id="edit-name"
                            name="name"
                            :default-value="editing.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="editing = null"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <Spinner v-if="processing" />
                            Save changes
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="deleting !== null"
            @update:open="(open) => !open && (deleting = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete task type</DialogTitle>
                </DialogHeader>
                <Alert variant="destructive">
                    <TriangleAlert class="h-4 w-4" />
                    <AlertTitle>Confirm deletion</AlertTitle>
                    <AlertDescription>
                        This will delete "{{ deleting?.name }}". This action
                        cannot be undone.
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
                        Delete task type
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
