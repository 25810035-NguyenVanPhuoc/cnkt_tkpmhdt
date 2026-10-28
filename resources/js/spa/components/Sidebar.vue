<script setup>
import { reactive } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth-store';
import { navConfig } from '../router/nav-config';
import { useSidebar } from '../composables/sidebar';

const auth = useAuthStore();
const route = useRoute();
const { isExpanded, isMobileOpen, closeMobileSidebar } = useSidebar();

const ICONS = {
    home: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    cart: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m-8 4a2 2 0 104 0 2 2 0 00-4 0zm8 0a2 2 0 104 0 2 2 0 00-4 0z',
    box: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    archive: 'M5 8h14M5 8a2 2 0 01-2-2V4a2 2 0 012-2h14a2 2 0 012 2v2a2 2 0 01-2 2M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
    users: 'M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    'id-badge': 'M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm4 3h.01M11 10h6M7 14h.01M11 14h6',
    percent: 'M15 5L5 19M6.5 8a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM17.5 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
    cog: [
        'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
        'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    ],
};

function iconPaths(icon) {
    const value = ICONS[icon] || ICONS.box;
    return Array.isArray(value) ? value : [value];
}

function isModuleVisible(module) {
    return auth.isModulePermitted(module);
}

const visibleItems = navConfig
    .map((item) => {
        if (item.type === 'group') {
            const children = item.children.filter((c) => isModuleVisible(c.module));
            return children.length ? { ...item, children } : null;
        }
        return isModuleVisible(item.module) ? item : null;
    })
    .filter(Boolean);

function isLinkActive(routeName) {
    return route.name === routeName;
}

function isGroupActive(item) {
    return item.children?.some((c) => isLinkActive(c.route)) ?? false;
}

const openGroups = reactive(new Set());

function toggleGroup(label) {
    if (openGroups.has(label)) openGroups.delete(label);
    else openGroups.add(label);
}

function isGroupOpen(item) {
    return openGroups.has(item.label) || isGroupActive(item);
}

function startTransition(el) {
    el.style.height = 'auto';
    const height = el.scrollHeight;
    el.style.height = '0px';
    el.offsetHeight;
    el.style.height = height + 'px';
}

function endTransition(el) {
    el.style.height = '';
}
</script>

<template>
    <div
        v-if="isMobileOpen"
        class="fixed inset-0 z-40 bg-gray-900/40 lg:hidden"
        @click="closeMobileSidebar"
    ></div>

    <aside
        :class="[
            'fixed top-0 left-0 z-50 flex h-screen flex-col border-r border-gray-200 bg-white transition-all duration-300 ease-in-out',
            isExpanded ? 'w-64' : 'w-18',
            isMobileOpen ? 'translate-x-0 w-64' : '-translate-x-full lg:translate-x-0',
        ]"
    >
        <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-200 px-4">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-700 text-sm font-bold text-white">
                Q
            </div>
            <span v-if="isExpanded || isMobileOpen" class="truncate text-sm font-bold text-gray-900">Quản lý Bán hàng</span>
        </div>

        <nav class="no-scrollbar flex flex-1 flex-col gap-1 overflow-y-auto p-3">
            <template v-for="item in visibleItems" :key="item.label">
                <button
                    v-if="item.type === 'group'"
                    type="button"
                    class="menu-item group"
                    :class="isGroupActive(item) ? 'menu-item-active' : 'menu-item-inactive'"
                    :title="!(isExpanded || isMobileOpen) ? item.label : ''"
                    @click="toggleGroup(item.label)"
                >
                    <svg class="h-5 w-5 shrink-0" :class="isGroupActive(item) ? 'menu-item-icon-active' : 'menu-item-icon-inactive'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path v-for="d in iconPaths(item.icon)" :key="d" stroke-linecap="round" stroke-linejoin="round" :d="d" />
                    </svg>
                    <span v-if="isExpanded || isMobileOpen" class="truncate">{{ item.label }}</span>
                    <svg
                        v-if="isExpanded || isMobileOpen"
                        class="ml-auto h-4 w-4 shrink-0 transition-transform duration-200"
                        :class="{ 'rotate-180 text-violet-700': isGroupOpen(item) }"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <router-link
                    v-else
                    :to="{ name: item.route }"
                    class="menu-item group"
                    :class="isLinkActive(item.route) ? 'menu-item-active' : 'menu-item-inactive'"
                    :title="!(isExpanded || isMobileOpen) ? item.label : ''"
                    @click="closeMobileSidebar"
                >
                    <svg class="h-5 w-5 shrink-0" :class="isLinkActive(item.route) ? 'menu-item-icon-active' : 'menu-item-icon-inactive'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path v-for="d in iconPaths(item.icon)" :key="d" stroke-linecap="round" stroke-linejoin="round" :d="d" />
                    </svg>
                    <span v-if="isExpanded || isMobileOpen" class="truncate">{{ item.label }}</span>
                </router-link>

                <transition
                    v-if="item.type === 'group'"
                    @enter="startTransition"
                    @after-enter="endTransition"
                    @before-leave="startTransition"
                    @after-leave="endTransition"
                >
                    <div v-show="isGroupOpen(item) && (isExpanded || isMobileOpen)" class="overflow-hidden">
                        <ul class="mt-1 ml-9 space-y-1">
                            <li v-for="child in item.children" :key="child.route">
                                <router-link
                                    :to="{ name: child.route }"
                                    class="menu-dropdown-item"
                                    :class="isLinkActive(child.route) ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'"
                                    @click="closeMobileSidebar"
                                >
                                    {{ child.label }}
                                </router-link>
                            </li>
                        </ul>
                    </div>
                </transition>
            </template>
        </nav>
    </aside>
</template>
