document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('cart-items-body');
    var emptyBox = document.getElementById('cart-empty');
    var totalEl = document.getElementById('cart-total');
    var clearBtn = document.getElementById('cart-clear-btn');
    var tableWrap = document.querySelector('.wrap-table-shopping-cart');

    function render() {
        var items = Cart.items();
        tbody.innerHTML = '';

        if (items.length === 0) {
            emptyBox.style.display = 'block';
            tableWrap.style.display = 'none';
        } else {
            emptyBox.style.display = 'none';
            tableWrap.style.display = '';
        }

        items.forEach(function (item) {
            var tr = document.createElement('tr');
            tr.className = 'table_row';
            tr.innerHTML =
                '<td class="column-1"><div class="how-itemcart1"><img src="' + item.image + '" alt="' + item.name + '" style="width:70px;height:70px;object-fit:cover;"></div></td>' +
                '<td class="column-1">' + item.name + '</td>' +
                '<td class="column-1">' + Cart.formatMoney(item.price) + ' ₫</td>' +
                '<td class="column-1"><select class="form-control" style="width:100px;"><option>Black</option><option>Gold</option><option>Silver</option><option>Purple</option><option>Titan</option></select></td>' +
                '<td class="column-1"><select class="form-control" style="width:100px;"><option>128G</option><option>256G</option><option>512G</option><option>1TB</option></select></td>' +
                '<td class="column-1"><input type="number" min="1" value="' + item.qty + '" class="form-control js-qty-input" data-id="' + item.id + '" style="width:70px;text-align:center;"></td>' +
                '<td class="column-1">' + Cart.formatMoney(item.qty * item.price) + ' ₫</td>' +
                '<td class="column-1"><a href="#" class="js-remove-btn" data-id="' + item.id + '">Xóa</a></td>';
            tbody.appendChild(tr);
        });

        totalEl.textContent = Cart.formatMoney(Cart.amount());
    }

    tbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-qty-input')) {
            var id = Number(e.target.dataset.id);
            var qty = parseInt(e.target.value, 10) || 1;
            Cart.setQty(id, qty);
            Cart.notify();
        }
    });

    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-remove-btn');
        if (btn) {
            e.preventDefault();
            Cart.remove(Number(btn.dataset.id));
            Cart.notify();
        }
    });

    clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        Cart.clear();
        Cart.notify();
    });

    // Dong bo khi gio hang bi doi tu noi khac tren CUNG trang (vd drawer
    // giot hang o header) - render() o day khong tu goi Cart.notify() nen
    // khong bi lap vo han qua lai voi event nay.
    window.addEventListener('cart:updated', render);

    render();
});
