document.addEventListener('DOMContentLoaded', function () {
    var itemsBox = document.getElementById('checkout-items');
    var totalEl = document.getElementById('checkout-total');
    var form = document.getElementById('checkout-form');
    var errorsBox = document.getElementById('checkout-errors');
    var successBox = document.getElementById('checkout-success');
    var orderCodeEl = document.getElementById('order-code');
    var submitBtn = form.querySelector('button[type="submit"]');

    function renderSummary() {
        var items = Cart.items();

        itemsBox.innerHTML = items.map(function (item) {
            return '<div class="flex-w flex-t p-b-13" style="border-bottom:1px solid #eee;">' +
                '<div style="flex:1;">' + item.name + ' x' + item.qty + '</div>' +
                '<div>' + Cart.formatMoney(item.qty * item.price) + ' ₫</div>' +
                '</div>';
        }).join('');

        totalEl.textContent = Cart.formatMoney(Cart.amount()) + ' ₫';

        if (items.length === 0) {
            submitBtn.disabled = true;
        }
    }

    renderSummary();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorsBox.style.display = 'none';

        var items = Cart.items();
        if (items.length === 0) {
            return;
        }

        var payload = {
            customer_name: form.customer_name.value,
            customer_phone: form.customer_phone.value,
            shipping_address: form.shipping_address.value,
            note: form.note.value || null,
            items: items.map(function (item) {
                return { product_id: item.id, quantity: item.qty };
            }),
        };

        submitBtn.disabled = true;

        fetch('/api/storefront/orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (result) {
                submitBtn.disabled = false;

                if (!result.ok) {
                    var messages = result.data.errors
                        ? Object.values(result.data.errors).flat().join(' ')
                        : (result.data.message || 'Có lỗi xảy ra, vui lòng thử lại.');
                    errorsBox.textContent = messages;
                    errorsBox.style.display = 'block';
                    return;
                }

                Cart.clear();
                form.style.display = 'none';
                successBox.style.display = 'block';
                orderCodeEl.textContent = result.data.data.code;

                var badge = document.querySelector('.js-cart-count');
                if (badge) {
                    badge.setAttribute('data-notify', Cart.count());
                }
            })
            .catch(function () {
                submitBtn.disabled = false;
                errorsBox.textContent = 'Không thể kết nối tới máy chủ, vui lòng thử lại.';
                errorsBox.style.display = 'block';
            });
    });
});
