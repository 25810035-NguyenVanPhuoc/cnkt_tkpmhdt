document.addEventListener('DOMContentLoaded', function () {
    var badge = document.querySelector('.js-cart-count');
    if (badge) {
        badge.setAttribute('data-notify', Cart.count());
    }
});
