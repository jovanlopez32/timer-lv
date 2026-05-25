<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Copy, Pencil, Trash2, TriangleAlert } from 'lucide-vue-next';
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

type Agent = {
    id: number;
    name: string;
    slug: string;
    brand_id: number;
    brand: string | null;
    created_at: string;
};

const props = defineProps<{ agents: Agent[]; brands: Brand[] }>();

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
const deleting = ref<Agent | null>(null);

const newBrandId = ref<string | undefined>(
    props.brands.length > 0 ? String(props.brands[0].id) : undefined,
);
const editBrandId = ref<string | undefined>(undefined);

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
    deleting.value = agent;
};

const confirmDestroy = () => {
    if (!deleting.value) {
        return;
    }

    const agent = deleting.value;
    deleting.value = null;
    router.delete(`/admin/agents/${agent.id}`, { preserveScroll: true });
};

const openEdit = (agent: Agent) => {
    editing.value = { ...agent };
    editBrandId.value = String(agent.brand_id);
};
</script>

<template>
    <Head title="Agents" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <header>
            <h1 class="text-2xl font-semibold">Agents</h1>
            <p class="text-sm text-muted-foreground">
                Create an agent, pick a brand, and share their public timer
                link.
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
            before adding agents.
        </section>

        <section v-else class="space-y-4">
            <h2 class="text-lg font-semibold">New agent</h2>
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
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Create agent
                </Button>
            </Form>
        </section>

        <section class="overflow-x-auto rounded-lg border text-card-foreground">
            <Table class="min-w-[760px]">
                <TableHeader>
                    <TableRow>
                        <TableHead class="px-5">Name</TableHead>
                        <TableHead class="px-5">Brand</TableHead>
                        <TableHead class="px-5">Public link</TableHead>
                        <TableHead class="w-72 px-5 text-right"
                            >Actions</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="agents.length === 0" :colspan="4">
                        No agents yet.
                    </TableEmpty>
                    <TableRow v-for="agent in agents" :key="agent.id">
                        <TableCell class="px-5 py-4 font-medium">
                            {{ agent.name }}
                        </TableCell>
                        <TableCell class="px-5 py-4">{{
                            agent.brand ?? '—'
                        }}</TableCell>
                        <TableCell class="px-5 py-4">
                            <Link
                                :href="`/${agent.slug}`"
                                class="font-mono text-sm text-primary hover:underline"
                            >
                                /{{ agent.slug }}
                            </Link>
                        </TableCell>
                        <TableCell class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    @click="copyLink(agent)"
                                    :title="
                                        justCopied === agent.id
                                            ? 'Copied!'
                                            : 'Copy link'
                                    "
                                >
                                    <Copy class="mr-2 h-4 w-4" />
                                    {{
                                        justCopied === agent.id
                                            ? 'Copied'
                                            : 'Copy'
                                    }}
                                </Button>
                                <Button
                                    variant="default"
                                    size="sm"
                                    @click="openEdit(agent)"
                                    title="Edit"
                                >
                                    <Pencil class="mr-2 h-4 w-4" />
                                    Edit
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="destroy(agent)"
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
                    <DialogTitle>Edit agent</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="editing"
                    :action="`/admin/agents/${editing.id}`"
                    method="patch"
                    v-slot="{ errors, processing }"
                    @success="editing = null"
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
                    <DialogTitle>Delete agent</DialogTitle>
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
                        Delete agent
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
