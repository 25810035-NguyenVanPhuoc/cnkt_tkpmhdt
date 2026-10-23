document.addEventListener('DOMContentLoaded', function () {
    var panel = document.querySelector('.js-panel-cart');
    var itemsList = document.getElementById('cart-drawer-items');
    var emptyBox = document.getElementById('cart-drawer-empty');
    var totalEl = document.getElementById('cart-drawer-total');

    if (!panel || !itemsList) {
        return;
    }

    function render() {
        var items = Cart.items();

        itemsList.innerHTML = items.map(function (item) {
            return '<li class="flex-w flex-t m-b-12" style="border:1px solid #eee; border-radius:8px; padding:12px;">' +
                '<div style="width:60px; height:60px; flex-shrink:0; border-radius:6px; overflow:hidden;">' +
                    '<img src="' + item.image + '" alt="' + item.name + '" style="width:100%; height:100%; object-fit:cover;">' +
                '</div>' +
                '<div style="flex:1; min-width:0; padding-left:12px;">' +
                    '<div class="stext-110 cl2" style="font-weight:600; margin-bottom:8px;">' + item.name + '</div>' +
                    '<div class="flex-w flex-sb-m">' +
                        '<div class="flex-w flex-m" style="border:1px solid #e6e6e6; border-radius:4px; overflow:hidden;">' +
                            '<button type="button" class="js-drawer-qty-minus" data-id="' + item.id + '" ' +
                                'style="width:26px; height:30px; border:none; background:#f7f7f7; cursor:pointer; font-size:16px; line-height:1;">&minus;</button>' +
                            '<input type="number" min="1" value="' + item.qty + '" class="js-drawer-qty-input" data-id="' + item.id + '" ' +
                                'style="width:36px; height:30px; text-align:center; border:none; border-left:1px solid #e6e6e6; border-right:1px solid #e6e6e6;">' +
                            '<button type="button" class="js-drawer-qty-plus" data-id="' + item.id + '" ' +
                                'style="width:26px; height:30px; border:none; background:#f7f7f7; cursor:pointer; font-size:16px; line-height:1;">+</button>' +
                        '</div>' +
                        '<span class="js-drawer-line-total" style="font-weight:700; color:#e04141;">' + Cart.formatMoney(item.qty * item.price) + ' ₫</span>' +
                    '</div>' +
                '</div>' +
                '<a href="#" class="js-drawer-remove" data-id="' + item.id + '" style="margin-left:8px; color:#bbb; font-size:16px;"><i class="fa fa-trash-o"></i></a>' +
            '</li>';
        }).join('');

        emptyBox.style.display = items.length === 0 ? 'block' : 'none';
        totalEl.textContent = Cart.formatMoney(Cart.amount()) + ' ₫';
    }

    document.querySelectorAll('.js-show-cart').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            render();
            panel.classList.add('show-header-cart');
        });
    });

    panel.querySelectorAll('.js-hide-cart').forEach(function (el) {
        el.addEventListener('click', function () {
            panel.classList.remove('show-header-cart');
        });
    });

    itemsList.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('.js-drawer-remove');
        if (removeBtn) {
            e.preventDefault();
            Cart.remove(Number(removeBtn.dataset.id));
            Cart.notify();
            return;
        }

        var stepBtn = e.target.closest('.js-drawer-qty-minus, .js-drawer-qty-plus');
        if (stepBtn) {
            e.preventDefault();
            var id = Number(stepBtn.dataset.id);
            var current = Cart.items().find(function (item) { return item.id === id; });
            if (!current) {
                return;
            }

            var delta = stepBtn.classList.contains('js-drawer-qty-plus') ? 1 : -1;
            Cart.setQty(id, current.qty + delta);
            Cart.notify();
        }
    });

    itemsList.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-drawer-qty-input')) {
            var id = Number(e.target.dataset.id);
            var qty = parseInt(e.target.value, 10) || 1;
            Cart.setQty(id, qty);
            Cart.notify();
        }
    });

    window.addEventListener('cart:updated', render);

    render();
});
