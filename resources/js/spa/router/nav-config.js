export const navConfig = [
    { label: 'Tổng quan', route: 'dashboard', module: null, icon: 'home' },
    { label: 'Bán hàng', route: 'sales.list', module: 'sales', icon: 'cart' },
    { label: 'Sản phẩm', route: 'catalog.products.list', module: 'products', icon: 'box' },
    { label: 'Danh mục', route: 'catalog.categories.list', module: 'categories', icon: 'tag' },
    { label: 'Kho hàng', route: 'warehouse.stock.list', module: 'warehouse', icon: 'archive' },
    { label: 'Nhập hàng / NCC', route: 'purchasing.orders.list', module: 'purchasing', icon: 'truck' },
    { label: 'Khách hàng', route: 'customers.list', module: 'customers', icon: 'users' },
    { label: 'Nhân viên', route: 'employees.list', module: 'employees', icon: 'id-badge' },
    { label: 'Khuyến mãi', route: 'promotions.list', module: 'promotions', icon: 'percent' },
    { label: 'Phân quyền', route: 'settings.roles.list', module: 'roles', icon: 'shield' },
    { label: 'Cài đặt hệ thống', route: 'settings.general', module: 'settings', icon: 'cog' },
];
