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

type Brand = {
    id: number;
    name: string;
    slug: string;
    agents_count: number;
    task_types_count: number;
    created_at: string;
};

defineProps<{ brands: Brand[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Settings', href: '/admin/brands' },
            { title: 'Brands', href: '/admin/brands' },
        ],
    },
});

const editing = ref<Brand | null>(null);

const openEdit = (brand: Brand) => {
    editing.value = { ...brand };
};

const destroy = (brand: Brand) => {
    if (
        !window.confirm(
            `Delete brand "${brand.name}"? This will also delete its ${brand.agents_count} agent(s) and ${brand.task_types_count} task type(s).`,
        )
    ) {
        return;
    }
    router.delete(`/admin/brands/${brand.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Brands" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Brands</h1>
            <p class="text-sm text-muted-foreground">
                Brands group agents and their task types.
            </p>
        </header>

        <section
            class="rounded-xl border bg-card p-6 text-card-foreground shadow-sm"
        >
            <h2 class="mb-4 text-lg font-semibold">New brand</h2>
            <Form
                action="/admin/brands"
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
                    Create brand
                </Button>
            </Form>
        </section>

        <section class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead class="text-right">Agents</TableHead>
                        <TableHead class="text-right">Task types</TableHead>
                        <TableHead class="w-32 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="brands.length === 0" :colspan="4">
                        No brands yet.
                    </TableEmpty>
                    <TableRow v-for="brand in brands" :key="brand.id">
                        <TableCell class="font-medium">
                            {{ brand.name }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ brand.agents_count }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ brand.task_types_count }}
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="openEdit(brand)"
                                    title="Edit"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="destroy(brand)"
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
                    <DialogTitle>Edit brand</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="editing"
                    :action="`/admin/brands/${editing.id}`"
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
