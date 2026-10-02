document.addEventListener('DOMContentLoaded', function () {
    var addBtn = document.getElementById('add-to-cart-btn');
    var buyBtn = document.getElementById('buy-now-btn');
    if (!addBtn) {
        return;
    }

    var qtyInput = document.getElementById('product-qty');
    var downBtn = document.querySelector('.js-qty-down');
    var upBtn = document.querySelector('.js-qty-up');

    if (downBtn) {
        downBtn.addEventListener('click', function () {
            var value = parseInt(qtyInput.value, 10) || 1;
            qtyInput.value = Math.max(1, value - 1);
        });
    }

    if (upBtn) {
        upBtn.addEventListener('click', function () {
            var value = parseInt(qtyInput.value, 10) || 1;
            qtyInput.value = value + 1;
        });
    }

    function readProduct(btn) {
        return {
            id: Number(btn.dataset.id),
            name: btn.dataset.name,
            price: Number(btn.dataset.price),
            image: btn.dataset.image,
        };
    }

    function updateBadge() {
        var badge = document.querySelector('.js-cart-count');
        if (badge) {
            badge.setAttribute('data-notify', Cart.count());
        }
    }

    addBtn.addEventListener('click', function () {
        var qty = parseInt(qtyInput.value, 10) || 1;
        Cart.add(readProduct(addBtn), qty);
        updateBadge();

        if (window.swal) {
            swal('Thành công', 'Đã thêm vào giỏ hàng', 'success');
        }
    });

    if (buyBtn) {
        buyBtn.addEventListener('click', function () {
            var qty = parseInt(qtyInput.value, 10) || 1;
            Cart.add(readProduct(buyBtn), qty);
            updateBadge();
            window.location.href = '/cua-hang/gio-hang';
        });
    }
});
