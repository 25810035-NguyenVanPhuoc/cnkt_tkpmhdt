document.addEventListener('DOMContentLoaded', function () {
    var itemsBox = document.getElementById('checkout-items');
    var totalEl = document.getElementById('checkout-total');
    var form = document.getElementById('checkout-form');
    var errorsBox = document.getElementById('checkout-errors');
    var successBox = document.getElementById('checkout-success');
    var orderCodeEl = document.getElementById('order-code');
    var orderDiscountEl = document.getElementById('order-discount');
    var orderDiscountAmountEl = document.getElementById('order-discount-amount');
    var submitBtn = form.querySelector('button[type="submit"]');

    var promoInput = document.getElementById('promo-code-input');
    var promoApplyBtn = document.getElementById('promo-apply-btn');
    var promoErrorsBox = document.getElementById('promo-errors');
    var promoSuccessBox = document.getElementById('promo-success');
    var promoBreakdownBox = document.getElementById('promo-breakdown');
    var promoOriginalPriceEl = document.getElementById('promo-original-price');
    var promoDiscountAmountEl = document.getElementById('promo-discount-amount');

    var appliedPromo = null; // { code, discountTotal } | null - reset khi reload trang

    function renderSummary() {
        var items = Cart.items();
        var subtotal = Cart.amount();

        itemsBox.innerHTML = items.map(function (item) {
            return '<div class="flex-w flex-t p-b-13" style="border-bottom:1px solid #eee;">' +
                '<div style="position:relative; width:50px; height:50px; margin-right:12px; flex-shrink:0;">' +
                    '<img src="' + item.image + '" alt="' + item.name + '" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">' +
                    '<span style="position:absolute; top:-6px; right:-6px; background:#222; color:#fff; font-size:11px; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center;">' + item.qty + '</span>' +
                '</div>' +
                '<div style="flex:1;" class="stext-110 cl2">' + item.name + '</div>' +
                '<div class="stext-110 cl2">' + Cart.formatMoney(item.qty * item.price) + ' ₫</div>' +
                '</div>';
        }).join('');

        var discount = appliedPromo ? appliedPromo.discountTotal : 0;
        totalEl.textContent = Cart.formatMoney(subtotal - discount) + ' ₫';

        if (appliedPromo && discount > 0) {
            promoOriginalPriceEl.textContent = Cart.formatMoney(subtotal) + ' ₫';
            promoDiscountAmountEl.textContent = '-' + Cart.formatMoney(discount) + ' ₫';
            promoBreakdownBox.style.display = '';
        } else {
            promoBreakdownBox.style.display = 'none';
        }

        if (items.length === 0) {
            submitBtn.disabled = true;
        }
    }

    function invalidatePromo() {
        if (!appliedPromo) {
            return;
        }

        appliedPromo = null;
        promoApplyBtn.textContent = 'Áp dụng';
        promoInput.readOnly = false;
        promoSuccessBox.style.display = 'none';
        renderSummary();
    }

    promoInput.addEventListener('input', invalidatePromo);

    // 'storage' bat thay doi tu TAB KHAC, 'cart:updated' bat thay doi ngay
    // tren trang nay (vd mo drawer gio hang o header roi doi so luong) - can
    // ca 2 vi 'storage' khong bao gio ban cho chinh tab vua ghi localStorage.
    window.addEventListener('storage', function (e) {
        if (e.key === 'storefront_cart') {
            invalidatePromo();
            renderSummary();
        }
    });

    window.addEventListener('cart:updated', function () {
        invalidatePromo();
        renderSummary();
    });

    promoApplyBtn.addEventListener('click', function () {
        promoErrorsBox.style.display = 'none';

        if (appliedPromo) {
            promoInput.value = '';
            invalidatePromo();
            return;
        }

        var code = promoInput.value.trim();
        if (!code) {
            promoErrorsBox.textContent = 'Vui lòng nhập mã khuyến mãi.';
            promoErrorsBox.style.display = 'block';
            return;
        }

        var cartItems = Cart.items();
        if (cartItems.length === 0) {
            return;
        }

        promoApplyBtn.disabled = true;
        promoApplyBtn.textContent = 'Đang kiểm tra...';

        var csrfTokenPromo = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('/thanh-toan/khuyen-mai', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfTokenPromo },
            body: JSON.stringify({
                promotion_code: code,
                items: cartItems.map(function (item) {
                    return { product_id: item.id, quantity: item.qty };
                }),
            }),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (result) {
                promoApplyBtn.disabled = false;

                if (!result.ok) {
                    var messages = result.data.errors
                        ? Object.values(result.data.errors).flat().join(' ')
                        : (result.data.message || 'Không áp dụng được mã khuyến mãi.');
                    promoApplyBtn.textContent = 'Áp dụng';
                    promoErrorsBox.textContent = messages;
                    promoErrorsBox.style.display = 'block';
                    return;
                }

                appliedPromo = {
                    code: result.data.data.promotion_code,
                    discountTotal: result.data.data.discount_total,
                };
                promoApplyBtn.textContent = 'Hủy áp dụng';
                promoInput.readOnly = true;
                promoSuccessBox.textContent = 'Đã áp dụng mã "' + appliedPromo.code + '".';
                promoSuccessBox.style.display = 'block';
                renderSummary();
            })
            .catch(function () {
                promoApplyBtn.disabled = false;
                promoApplyBtn.textContent = 'Áp dụng';
                promoErrorsBox.textContent = 'Không thể kết nối tới máy chủ, vui lòng thử lại.';
                promoErrorsBox.style.display = 'block';
            });
    });

    renderSummary();

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorsBox.style.display = 'none';

        var items = Cart.items();
        if (items.length === 0) {
            return;
        }

        var typedCode = promoInput.value.trim();
        if (typedCode && (!appliedPromo || appliedPromo.code !== typedCode)) {
            errorsBox.textContent = 'Vui lòng nhấn "Áp dụng" để kiểm tra mã khuyến mãi trước khi đặt hàng.';
            errorsBox.style.display = 'block';
            promoInput.focus();
            return;
        }

        var payload = {
            customer_name: form.customer_name.value,
            customer_phone: form.customer_phone.value,
            shipping_address: form.shipping_address.value,
            promotion_code: appliedPromo ? appliedPromo.code : null,
            note: form.note.value || null,
            items: items.map(function (item) {
                return { product_id: item.id, quantity: item.qty };
            }),
        };

        submitBtn.disabled = true;

        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Goi qua route web (co session) thay vi /api/storefront/orders
        // (khong co session) - de don hang tu dong gan dung khach hang dang
        // dang nhap (neu co), khong chi dua vao SDT nhap tren form.
        fetch('/thanh-toan', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
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

                if (result.data.data.discount_total > 0) {
                    orderDiscountAmountEl.textContent = Cart.formatMoney(result.data.data.discount_total) + ' ₫';
                    orderDiscountEl.style.display = 'block';
                }

                Cart.notify();
            })
            .catch(function () {
                submitBtn.disabled = false;
                errorsBox.textContent = 'Không thể kết nối tới máy chủ, vui lòng thử lại.';
                errorsBox.style.display = 'block';
            });
    });
});
