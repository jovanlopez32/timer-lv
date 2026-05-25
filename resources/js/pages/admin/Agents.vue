<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Copy, Pencil, Trash2 } from 'lucide-vue-next';
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

type Agent = {
    id: number;
    name: string;
    slug: string;
    brand: string;
    created_at: string;
};

defineProps<{ agents: Agent[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Settings', href: '/admin/agents' },
            { title: 'Agents', href: '/admin/agents' },
        ],
    },
});

const justCopied = ref<number | null>(null);
const editing = ref<Agent | null>(null);

const publicUrl = (slug: string) => `${window.location.origin}/${slug}`;

const copyLink = async (agent: Agent) => {
    await navigator.clipboard.writeText(publicUrl(agent.slug));
    justCopied.value = agent.id;
    window.setTimeout(() => {
        if (justCopied.value === agent.id) {
            justCopied.value = null;
        }
    }, 1500);
};

const destroy = (agent: Agent) => {
    if (!window.confirm(`Delete agent "${agent.name}"?`)) {
        return;
    }
    router.delete(`/admin/agents/${agent.id}`, { preserveScroll: true });
};

const openEdit = (agent: Agent) => {
    editing.value = { ...agent };
};
</script>

<template>
    <Head title="Agents" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Agents</h1>
            <p class="text-sm text-muted-foreground">
                Create an agent and share their public timer link.
            </p>
        </header>

        <section
            class="rounded-xl border bg-card p-6 text-card-foreground shadow-sm"
        >
            <h2 class="mb-4 text-lg font-semibold">New agent</h2>
            <Form
                action="/admin/agents"
                method="post"
                :reset-on-success="true"
                v-slot="{ errors, processing }"
                class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" name="name" required autocomplete="off" />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="brand">Brand</Label>
                    <Input
                        id="brand"
                        name="brand"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.brand" />
                </div>
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Create agent
                </Button>
            </Form>
        </section>

        <section class="rounded-xl border bg-card text-card-foreground shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Brand</TableHead>
                        <TableHead>Public link</TableHead>
                        <TableHead class="w-40 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="agents.length === 0" :colspan="4">
                        No agents yet.
                    </TableEmpty>
                    <TableRow v-for="agent in agents" :key="agent.id">
                        <TableCell class="font-medium">
                            {{ agent.name }}
                        </TableCell>
                        <TableCell>{{ agent.brand }}</TableCell>
                        <TableCell>
                            <Link
                                :href="`/${agent.slug}`"
                                class="font-mono text-sm text-primary hover:underline"
                            >
                                /{{ agent.slug }}
                            </Link>
                        </TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="copyLink(agent)"
                                    :title="
                                        justCopied === agent.id
                                            ? 'Copied!'
                                            : 'Copy link'
                                    "
                                >
                                    <Copy class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="openEdit(agent)"
                                    title="Edit"
                                >
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="destroy(agent)"
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
                    <DialogTitle>Edit agent</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="editing"
                    :action="`/admin/agents/${editing.id}`"
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
                    <div class="grid gap-2">
                        <Label for="edit-brand">Brand</Label>
                        <Input
                            id="edit-brand"
                            name="brand"
                            :default-value="editing.brand"
                            required
                        />
                        <InputError :message="errors.brand" />
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
