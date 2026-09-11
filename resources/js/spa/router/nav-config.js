export const navConfig = [
    { type: 'link', label: 'Tổng quan', route: 'dashboard', module: null, icon: 'home' },
    { type: 'link', label: 'Bán hàng (POS)', route: 'sales.list', module: 'sales', icon: 'cart' },
    {
        type: 'group',
        label: 'Sản phẩm',
        icon: 'box',
        children: [
            { label: 'Danh sách sản phẩm', route: 'catalog.products.list', module: 'products' },
            { label: 'Danh mục', route: 'catalog.categories.list', module: 'categories' },
        ],
    },
    {
        type: 'group',
        label: 'Kho hàng',
        icon: 'archive',
        children: [
            { label: 'Tồn kho', route: 'warehouse.stock.list', module: 'warehouse' },
            { label: 'Nhập hàng / NCC', route: 'purchasing.orders.list', module: 'purchasing' },
        ],
    },
    { type: 'link', label: 'Khách hàng', route: 'customers.list', module: 'customers', icon: 'users' },
    { type: 'link', label: 'Nhân viên', route: 'employees.list', module: 'employees', icon: 'id-badge' },
    { type: 'link', label: 'Khuyến mãi', route: 'promotions.list', module: 'promotions', icon: 'percent' },
    {
        type: 'group',
        label: 'Cài đặt',
        icon: 'cog',
        children: [
            { label: 'Phân quyền', route: 'settings.roles.list', module: 'roles' },
            { label: 'Cài đặt hệ thống', route: 'settings.general', module: 'settings' },
        ],
    },
];
