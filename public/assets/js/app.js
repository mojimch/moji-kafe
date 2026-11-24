(() => {
    'use strict';

    const storageKey = 'mojiKafeCart';
    const feedback = document.getElementById('app-feedback');
    const cartCount = document.getElementById('cart-count');
    const currency = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

    function showMessage(message, type) {
        if (!feedback) {
            return;
        }

        feedback.replaceChildren();
        feedback.className = 'container pt-3';
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} mb-0`;
        alert.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        alert.textContent = message;
        feedback.append(alert);
    }

    function loadCart() {
        const serializedCart = localStorage.getItem(storageKey);
        if (serializedCart === null) {
            return [];
        }

        let cart;
        try {
            cart = JSON.parse(serializedCart);
        } catch {
            throw new Error('Data keranjang tidak dapat dibaca. Hapus data keranjang browser lalu coba lagi.');
        }

        const validCart = Array.isArray(cart) && cart.every((item) =>
            item !== null
            && Number.isSafeInteger(item.productId)
            && item.productId > 0
            && (item.variantId === null || (Number.isSafeInteger(item.variantId) && item.variantId > 0))
            && typeof item.productName === 'string'
            && (item.variantName === null || typeof item.variantName === 'string')
            && Number.isFinite(item.price)
            && item.price >= 0
            && Number.isSafeInteger(item.quantity)
            && item.quantity >= 1
            && item.quantity <= 99
        );

        if (!validCart) {
            throw new Error('Data keranjang tidak valid. Hapus data keranjang browser lalu coba lagi.');
        }

        return cart;
    }

    function updateCartCount() {
        if (!cartCount) {
            return;
        }

        try {
            const count = loadCart().reduce((total, item) => total + item.quantity, 0);
            cartCount.textContent = count > 99 ? '99+' : String(count);
            cartCount.setAttribute('aria-label', `Jumlah item dalam keranjang: ${count}`);
        } catch (error) {
            cartCount.textContent = '!';
            showMessage(error.message, 'danger');
        }
    }

    function updateDisplayedPrice(card) {
        const output = card.querySelector('[data-current-price]');
        if (!output) {
            return;
        }

        const selector = card.querySelector('[data-variant-select]');
        const selectedOption = selector ? selector.selectedOptions[0] : null;
        const price = Number(selectedOption?.dataset.price ?? card.dataset.basePrice);
        if (Number.isFinite(price) && price >= 0) {
            output.textContent = currency.format(price);
        }
    }

    document.addEventListener('change', (event) => {
        if (!(event.target instanceof HTMLSelectElement) || !event.target.matches('[data-variant-select]')) {
            return;
        }

        const card = event.target.closest('[data-product-card]');
        if (card) {
            updateDisplayedPrice(card);
        }
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const button = event.target.closest('[data-add-to-cart]');
        if (!button || button.disabled) {
            return;
        }

        const card = button.closest('[data-product-card]');
        if (!card) {
            showMessage('Data produk tidak dapat dibaca.', 'danger');
            return;
        }

        try {
            const selector = card.querySelector('[data-variant-select]');
            const selectedOption = selector ? selector.selectedOptions[0] : null;
            const variantId = selectedOption ? Number(selectedOption.value) : null;
            const price = Number(selectedOption?.dataset.price ?? card.dataset.basePrice);
            const productId = Number(card.dataset.productId);
            const productName = card.dataset.productName;
            const variantName = selectedOption?.dataset.variantName ?? null;

            if (
                !Number.isSafeInteger(productId)
                || productId <= 0
                || !productName
                || !Number.isFinite(price)
                || price < 0
                || (variantId !== null && (!Number.isSafeInteger(variantId) || variantId <= 0))
            ) {
                throw new Error('Informasi produk atau harga tidak valid.');
            }

            const cart = loadCart();
            const existing = cart.find((item) => item.productId === productId && item.variantId === variantId);
            if (existing && existing.quantity >= 99) {
                showMessage('Jumlah maksimal per item adalah 99.', 'warning');
                return;
            }

            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({
                    productId,
                    variantId,
                    productName,
                    variantName,
                    price,
                    quantity: 1,
                });
            }

            localStorage.setItem(storageKey, JSON.stringify(cart));
            updateCartCount();
            showMessage(`${productName}${variantName ? ` (${variantName})` : ''} ditambahkan ke keranjang.`, 'success');
        } catch (error) {
            showMessage(error.message || 'Keranjang tidak dapat diperbarui.', 'danger');
        }
    });

    document.querySelectorAll('[data-product-card]').forEach(updateDisplayedPrice);
    updateCartCount();
})();