<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings } from '@lucide/vue';
import { logout } from '@/actions/App/Http/Controllers/Api/AuthController';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useSidebar } from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import { home } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

// UserMenuContent dipakai di dalam sidebar (NavUser) DAN di AppHeader yang tak punya
// SidebarProvider. Beri fallback null agar useSidebar tidak throw di luar provider.
const sidebar = useSidebar(null);
const closeMobileSidebar = () => sidebar?.setOpenMobile(false);

const csrf = () => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

// Auth DuitBot memakai pola fetch + JSON (bukan Inertia). Endpoint logout membalas
// JSON, jadi tidak boleh dipanggil lewat Inertia <Link> — pakai fetch lalu redirect.
const handleLogout = async () => {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    await fetch(logout().url, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrf(),
        },
    });

    router.flushAll();
    window.location.href = home().url;
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="edit()"
                prefetch
                @click="closeMobileSidebar"
            >
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem
        class="cursor-pointer"
        data-test="logout-button"
        @select="handleLogout"
    >
        <LogOut class="mr-2 h-4 w-4" />
        Log out
    </DropdownMenuItem>
</template>
