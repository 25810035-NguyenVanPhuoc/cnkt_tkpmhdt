import { computed, inject, onMounted, onUnmounted, provide, ref } from 'vue';

const SidebarSymbol = Symbol('sidebar');

export function useSidebarProvider() {
    const isExpanded = ref(true);
    const isMobileOpen = ref(false);
    const isMobile = ref(false);

    function handleResize() {
        isMobile.value = window.innerWidth < 1024;
        if (!isMobile.value) isMobileOpen.value = false;
    }

    onMounted(() => {
        handleResize();
        window.addEventListener('resize', handleResize);
    });
    onUnmounted(() => window.removeEventListener('resize', handleResize));

    function toggleSidebar() {
        if (isMobile.value) isMobileOpen.value = !isMobileOpen.value;
        else isExpanded.value = !isExpanded.value;
    }

    const context = {
        isExpanded: computed(() => (isMobile.value ? false : isExpanded.value)),
        isMobileOpen,
        toggleSidebar,
        closeMobileSidebar: () => (isMobileOpen.value = false),
    };

    provide(SidebarSymbol, context);
    return context;
}

export function useSidebar() {
    const context = inject(SidebarSymbol);
    if (!context) {
        throw new Error('useSidebar phải dùng bên trong component có useSidebarProvider() ở component cha');
    }
    return context;
}
