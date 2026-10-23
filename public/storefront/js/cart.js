(function (window) {
    var STORAGE_KEY = 'storefront_cart';

    function load() {
        try {
            var json = localStorage.getItem(STORAGE_KEY);
            return json ? JSON.parse(json) : [];
        } catch (e) {
            return [];
        }
    }

    function save(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    }

    var Cart = {
        items: function () {
            return load();
        },
        add: function (product, qty) {
            qty = qty || 1;
            var items = load();
            var existing = items.find(function (item) {
                return item.id === product.id;
            });

            if (existing) {
                existing.qty += qty;
            } else {
                items.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    image: product.image,
                    qty: qty,
                });
            }

            save(items);
        },
        setQty: function (id, qty) {
            var items = load();
            var item = items.find(function (item) {
                return item.id === id;
            });

            if (!item) {
                return;
            }

            item.qty = Math.max(1, qty);
            save(items);
        },
        remove: function (id) {
            save(load().filter(function (item) {
                return item.id !== id;
            }));
        },
        clear: function () {
            save([]);
        },
        count: function () {
            return load().reduce(function (total, item) {
                return total + item.qty;
            }, 0);
        },
        amount: function () {
            return load().reduce(function (total, item) {
                return total + item.qty * item.price;
            }, 0);
        },
        formatMoney: function (n) {
            return Math.round(n).toLocaleString('vi-VN');
        },
        // Goi sau MOI lan thay doi gio hang (add/remove/setQty/clear). Cap
        // nhat badge tren header roi phat event 'cart:updated' - bat ky trang
        // nao dang hien thi gio hang (drawer, trang gio hang, trang thanh
        // toan) chi can lang nghe event nay de tu ve lai, tranh tung noi tu
        // goi thang qua lai nhau (de bi sot dong bo hoac lap vo han).
        notify: function () {
            var badge = document.querySelector('.js-cart-count');
            if (badge) {
                badge.setAttribute('data-notify', Cart.count());
            }
            window.dispatchEvent(new CustomEvent('cart:updated'));
        },
    };

    window.Cart = Cart;
})(window);
