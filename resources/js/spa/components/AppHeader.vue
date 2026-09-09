<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth-store';
import { useSidebar } from '../composables/sidebar';

const auth = useAuthStore();
const router = useRouter();
const { toggleSidebar } = useSidebar();

const userDropdownOpen = ref(false);
const rootRef = ref(null);

function toggleUserDropdown() {
    userDropdownOpen.value = !userDropdownOpen.value;
}

function handleClickOutside(event) {
    if (rootRef.value && !rootRef.value.contains(event.target)) {
        userDropdownOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

async function handleLogout() {
    await auth.logout();
    router.push({ name: 'login' });
}

function avatarLetter() {
    return (auth.user?.name ?? '?').charAt(0).toUpperCase();
}

const primaryRole = () => auth.user?.roles?.[0] ?? '';
</script>

<template>
    <header ref="rootRef" class="sticky top-0 z-30 flex w-full border-b border-gray-200 bg-white">
        <div class="flex w-full items-center justify-between gap-3 px-4 py-3 lg:px-6">
            <button
                type="button"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100"
                aria-label="Thu gọn / mở rộng sidebar"
                @click="toggleSidebar"
            >
                <svg width="18" height="14" viewBox="0 0 16 12" fill="none">
                    <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                        fill="currentColor"
                    />
                </svg>
            </button>

            <div class="relative">
                <button type="button" class="flex items-center gap-2 text-gray-700" @click="toggleUserDropdown">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-violet-700 text-sm font-semibold text-white">
                        {{ avatarLetter() }}
                    </span>
                    <span class="hidden text-sm font-medium sm:block">{{ auth.user?.name }}</span>
                    <svg
                        class="h-4 w-4 shrink-0 transition-transform"
                        :class="{ 'rotate-180': userDropdownOpen }"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div
                    v-if="userDropdownOpen"
                    class="absolute right-0 mt-3 flex w-56 flex-col rounded-xl border border-gray-200 bg-white p-3 shadow-lg"
                >
                    <div class="border-b border-gray-100 pb-3">
                        <span class="block text-sm font-medium text-gray-800">{{ auth.user?.name }}</span>
                        <span class="mt-0.5 block text-xs text-gray-500">{{ primaryRole() }}</span>
                    </div>
                    <button
                        type="button"
                        class="mt-3 flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-100"
                        @click="handleLogout"
                    >
                        <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Đăng xuất
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>
