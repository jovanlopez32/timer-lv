<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { KeyRound, Pencil, Plus, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    created_at: string;
};

defineProps<{ users: AdminUser[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Settings', href: '/admin/users' },
            { title: 'Users', href: '/admin/users' },
        ],
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

const createOpen = ref(false);
const editing = ref<AdminUser | null>(null);
const resetting = ref<AdminUser | null>(null);
const deleting = ref<AdminUser | null>(null);

const openEdit = (user: AdminUser) => {
    editing.value = { ...user };
};
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <header class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Users</h1>
                <p class="text-sm text-muted-foreground">
                    Admin accounts that can sign in to this dashboard.
                </p>
            </div>
            <Button @click="createOpen = true">
                <Plus class="h-4 w-4" />
                Add user
            </Button>
        </header>

        <section class="overflow-x-auto rounded-lg border text-card-foreground">
            <Table class="min-w-[640px]">
                <TableHeader>
                    <TableRow>
                        <TableHead class="px-5">Name</TableHead>
                        <TableHead class="px-5">Email</TableHead>
                        <TableHead class="w-72 px-5 text-right"
                            >Actions</TableHead
                        >
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="users.length === 0" :colspan="3">
                        No users yet.
                    </TableEmpty>
                    <TableRow v-for="user in users" :key="user.id">
                        <TableCell class="px-5 py-4 font-medium">
                            {{ user.name }}
                        </TableCell>
                        <TableCell class="px-5 py-4">{{
                            user.email
                        }}</TableCell>
                        <TableCell class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    @click="resetting = user"
                                    title="Reset password"
                                >
                                    <KeyRound class="mr-2 h-4 w-4" />
                                    Password
                                </Button>
                                <Button
                                    variant="default"
                                    size="sm"
                                    @click="openEdit(user)"
                                    title="Edit"
                                >
                                    <Pencil class="mr-2 h-4 w-4" />
                                    Edit
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    :disabled="user.id === currentUserId"
                                    :title="
                                        user.id === currentUserId
                                            ? 'You cannot delete your own account'
                                            : 'Delete'
                                    "
                                    @click="deleting = user"
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

        <Dialog v-model:open="createOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Add user</DialogTitle>
                </DialogHeader>
                <Form
                    action="/admin/users"
                    method="post"
                    :reset-on-success="true"
                    v-slot="{ errors, processing }"
                    @success="createOpen = false"
                    class="grid gap-4"
                >
                    <div class="grid gap-2">
                        <Label for="create-name">Name</Label>
                        <Input
                            id="create-name"
                            name="name"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-email">Email</Label>
                        <Input
                            id="create-email"
                            name="email"
                            type="email"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-password">Password</Label>
                        <Input
                            id="create-password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError :message="errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="create-password_confirmation"
                            >Confirm password</Label
                        >
                        <Input
                            id="create-password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                        />
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
                            Create user
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="editing !== null"
            @update:open="(open) => !open && (editing = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit user</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="editing"
                    :action="`/admin/users/${editing.id}`"
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
                        <Label for="edit-email">Email</Label>
                        <Input
                            id="edit-email"
                            name="email"
                            type="email"
                            :default-value="editing.email"
                            required
                        />
                        <InputError :message="errors.email" />
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
                            Save changes
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="resetting !== null"
            @update:open="(open) => !open && (resetting = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reset password</DialogTitle>
                </DialogHeader>
                <Form
                    v-if="resetting"
                    :action="`/admin/users/${resetting.id}/password`"
                    method="put"
                    v-slot="{ errors, processing }"
                    @success="resetting = null"
                    class="grid gap-4"
                >
                    <p class="text-sm text-muted-foreground">
                        Set a new password for
                        <span class="font-medium text-foreground">{{
                            resetting.name
                        }}</span
                        >.
                    </p>
                    <div class="grid gap-2">
                        <Label for="reset-password">New password</Label>
                        <Input
                            id="reset-password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError :message="errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="reset-password_confirmation"
                            >Confirm new password</Label
                        >
                        <Input
                            id="reset-password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="resetting = null"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="processing">
                            Update password
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
                    <DialogTitle>Delete user</DialogTitle>
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
                    <Form
                        v-if="deleting"
                        :action="`/admin/users/${deleting.id}`"
                        method="delete"
                        v-slot="{ processing }"
                        @success="deleting = null"
                    >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                        >
                            Delete user
                        </Button>
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
