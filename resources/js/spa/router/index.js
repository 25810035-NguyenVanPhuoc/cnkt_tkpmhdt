import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth-store';

import BlankLayout from '../layouts/blank-layout.vue';
import DefaultLayout from '../layouts/default-layout.vue';

import LoginView from '../pages/auth/login.vue';
import DashboardView from '../pages/dashboard/index.vue';
import SalesListView from '../pages/sales/list.vue';
import ProductsListView from '../pages/catalog/products/list.vue';
import CategoriesListView from '../pages/catalog/categories/list.vue';
import StockListView from '../pages/warehouse/stock/list.vue';
import PurchaseOrdersListView from '../pages/purchasing/orders/list.vue';
import CustomersListView from '../pages/customers/list.vue';
import EmployeesListView from '../pages/employees/list.vue';
import PromotionsListView from '../pages/promotions/list.vue';
import RolesListView from '../pages/settings/roles/list.vue';
import GeneralSettingsView from '../pages/settings/general/form.vue';
import NotFoundView from '../pages/not-found/index.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: BlankLayout,
            children: [
                { path: 'login', name: 'login', component: LoginView, meta: { guestOnly: true } },
            ],
        },
        {
            path: '/',
            component: DefaultLayout,
            meta: { requiresAuth: true },
            children: [
                { path: '', redirect: '/dashboard' },
                { path: 'dashboard', name: 'dashboard', component: DashboardView },
                { path: 'sales', name: 'sales.list', component: SalesListView, meta: { module: 'sales' } },
                {
                    path: 'catalog/products',
                    name: 'catalog.products.list',
                    component: ProductsListView,
                    meta: { module: 'products' },
                },
                {
                    path: 'catalog/categories',
                    name: 'catalog.categories.list',
                    component: CategoriesListView,
                    meta: { module: 'categories' },
                },
                {
                    path: 'warehouse/stock',
                    name: 'warehouse.stock.list',
                    component: StockListView,
                    meta: { module: 'warehouse' },
                },
                {
                    path: 'purchasing/orders',
                    name: 'purchasing.orders.list',
                    component: PurchaseOrdersListView,
                    meta: { module: 'purchasing' },
                },
                {
                    path: 'customers',
                    name: 'customers.list',
                    component: CustomersListView,
                    meta: { module: 'customers' },
                },
                {
                    path: 'employees',
                    name: 'employees.list',
                    component: EmployeesListView,
                    meta: { module: 'employees' },
                },
                {
                    path: 'promotions',
                    name: 'promotions.list',
                    component: PromotionsListView,
                    meta: { module: 'promotions' },
                },
                {
                    path: 'settings/roles',
                    name: 'settings.roles.list',
                    component: RolesListView,
                    meta: { module: 'roles' },
                },
                {
                    path: 'settings/general',
                    name: 'settings.general',
                    component: GeneralSettingsView,
                    meta: { module: 'settings' },
                },
            ],
        },
        { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundView },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.guestOnly) {
        if (auth.token) return { name: 'dashboard' };
        return true;
    }

    if (!to.meta.requiresAuth) return true;

    if (!auth.token) return { name: 'login', query: { redirect: to.fullPath } };

    if (!auth.ready) {
        try {
            await auth.fetchMe();
        } catch {
            auth.logout();
            return { name: 'login', query: { redirect: to.fullPath } };
        }
    }

    const module = to.meta.module;
    if (module && !auth.isModulePermitted(module)) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
