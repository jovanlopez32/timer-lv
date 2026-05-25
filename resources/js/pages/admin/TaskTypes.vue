<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
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

type TaskType = {
    id: number;
    name: string;
    created_at: string;
};

defineProps<{ taskTypes: TaskType[] }>();

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

const openEdit = (taskType: TaskType) => {
    editing.value = { ...taskType };
};

const destroy = (taskType: TaskType) => {
    if (!window.confirm(`Delete task type "${taskType.name}"?`)) {
        return;
    }
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
                Categories that agents can pick when starting a timer.
            </p>
        </header>

        <section
            class="rounded-xl border bg-card p-6 text-card-foreground shadow-sm"
        >
            <h2 class="mb-4 text-lg font-semibold">New task type</h2>
            <Form
                action="/admin/task-types"
                method="post"
                :reset-on-success="true"
                v-slot="{ errors, processing }"
                class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end"
            >
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

        <section class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead class="w-32 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="taskTypes.length === 0" :colspan="2">
                        No task types yet.
                    </TableEmpty>
                    <TableRow
                        v-for="taskType in taskTypes"
                        :key="taskType.id"
                    >
                        <TableCell class="font-medium">
                            {{ taskType.name }}
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="openEdit(taskType)"
                                    title="Edit"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="destroy(taskType)"
                                    title="Delete"
                                >
                                    <Trash2 class="h-4 w-4 text-destructive" />
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
                    :options="{ onSuccess: () => (editing = null) }"
                    class="grid gap-4"
                >
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
    </div>
</template>
