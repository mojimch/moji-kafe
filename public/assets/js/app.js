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
        let serializedCart;
        try {
            serializedCart = localStorage.getItem(storageKey);
        } catch {
            throw new Error('Keranjang browser tidak dapat diakses. Periksa pengaturan penyimpanan browser.');
        }

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

    function makeQuantityButton(label, action, index, disabled = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary';
        button.dataset.cartAction = action;
        button.dataset.cartIndex = String(index);
        button.setAttribute('aria-label', label);
        button.textContent = action === 'increase' ? '+' : '−';
        button.disabled = disabled;
        return button;
    }

    function makeCartItem(item, index, controls) {
        const article = document.createElement('article');
        article.className = 'contact-panel p-3';

        const row = document.createElement('div');
        row.className = 'd-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3';

        const details = document.createElement('div');
        const title = document.createElement('h2');
        title.className = 'h6 fw-bold mb-1';
        title.textContent = item.productName;
        details.append(title);

        if (item.variantName) {
            const variant = document.createElement('p');
            variant.className = 'small text-secondary mb-1';
            variant.textContent = `Varian: ${item.variantName}`;
            details.append(variant);
        }

        const price = document.createElement('p');
        price.className = 'small text-secondary mb-0';
        price.textContent = `${currency.format(item.price)} × ${item.quantity}`;
        details.append(price);

        const actions = document.createElement('div');
        actions.className = 'd-flex align-items-center gap-2';
        if (controls) {
            actions.append(makeQuantityButton('Kurangi jumlah', 'decrease', index, item.quantity <= 1));

            const quantity = document.createElement('span');
            quantity.className = 'fw-semibold';
            quantity.textContent = String(item.quantity);
            quantity.setAttribute('aria-label', `Jumlah: ${item.quantity}`);
            actions.append(quantity);

            actions.append(makeQuantityButton('Tambah jumlah', 'increase', index, item.quantity >= 99));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger ms-1';
            remove.dataset.cartAction = 'remove';
            remove.dataset.cartIndex = String(index);
            remove.setAttribute('aria-label', `Hapus ${item.productName} dari keranjang`);
            const icon = document.createElement('i');
            icon.className = 'bi bi-trash';
            icon.setAttribute('aria-hidden', 'true');
            remove.append(icon);
            actions.append(remove);
        } else {
            const quantity = document.createElement('span');
            quantity.className = 'small text-secondary';
            quantity.textContent = `${item.quantity} ×`;
            actions.append(quantity);
        }

        const lineTotal = document.createElement('strong');
        lineTotal.className = 'text-success text-nowrap';
        lineTotal.textContent = currency.format(item.price * item.quantity);
        actions.append(lineTotal);

        row.append(details, actions);
        article.append(row);
        return article;
    }

    function renderCart(cart) {
        const container = document.querySelector('[data-cart-items]');
        if (!container) {
            return;
        }

        const emptyState = document.querySelector('[data-cart-empty]');
        const summary = document.querySelector('[data-cart-summary]');
        const totalOutput = document.querySelector('[data-cart-total]');
        container.replaceChildren();

        if (cart.length === 0) {
            emptyState?.removeAttribute('hidden');
            summary?.setAttribute('hidden', '');
            return;
        }

        emptyState?.setAttribute('hidden', '');
        summary?.removeAttribute('hidden');
        let total = 0;
        cart.forEach((item, index) => {
            container.append(makeCartItem(item, index, true));
            total += item.price * item.quantity;
        });
        if (totalOutput) {
            totalOutput.textContent = currency.format(total);
        }
    }

    function renderCheckoutSummary(cart) {
        const container = document.querySelector('[data-checkout-items]');
        if (!container) {
            return;
        }

        const emptyState = document.querySelector('[data-checkout-empty]');
        const totalOutput = document.querySelector('[data-checkout-total]');
        const submitButton = document.querySelector('[data-checkout-form] button[type="submit"]');
        container.replaceChildren();
        let total = 0;

        cart.forEach((item) => {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between gap-3 small';

            const label = document.createElement('span');
            label.className = 'text-break';
            label.textContent = `${item.productName}${item.variantName ? ` (${item.variantName})` : ''} × ${item.quantity}`;

            const amount = document.createElement('strong');
            amount.className = 'text-nowrap';
            amount.textContent = currency.format(item.price * item.quantity);
            row.append(label, amount);
            container.append(row);
            total += item.price * item.quantity;
        });

        emptyState?.toggleAttribute('hidden', cart.length > 0);
        if (totalOutput) {
            totalOutput.textContent = currency.format(total);
        }
        if (submitButton) {
            submitButton.disabled = cart.length === 0;
        }
    }

    function refreshCart() {
        try {
            const cart = loadCart();
            if (cartCount) {
                const count = cart.reduce((sum, item) => sum + item.quantity, 0);
                cartCount.textContent = count > 99 ? '99+' : String(count);
                cartCount.setAttribute('aria-label', `Jumlah item dalam keranjang: ${count}`);
            }
            renderCart(cart);
            renderCheckoutSummary(cart);
        } catch (error) {
            if (cartCount) {
                cartCount.textContent = '!';
                cartCount.setAttribute('aria-label', 'Data keranjang bermasalah');
            }
            renderCart([]);
            renderCheckoutSummary([]);
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

    function updateDineInTableVisibility() {
        const tableContainer = document.querySelector('[data-dine-in-table]');
        if (!tableContainer) {
            return;
        }

        const dineInSelected = document.querySelector('[data-order-type]:checked')?.value === 'dine_in';
        const tableSelect = tableContainer.querySelector('select');
        tableContainer.toggleAttribute('hidden', !dineInSelected);
        if (tableSelect) {
            tableSelect.disabled = !dineInSelected;
            tableSelect.required = dineInSelected;
        }
    }

    document.addEventListener('change', (event) => {
        if (!(event.target instanceof HTMLSelectElement) && !(event.target instanceof HTMLInputElement)) {
            return;
        }

        if (event.target.matches('[data-variant-select]')) {
            const card = event.target.closest('[data-product-card]');
            if (card) {
                updateDisplayedPrice(card);
            }
        } else if (event.target.matches('[data-order-type]')) {
            updateDineInTableVisibility();
        }
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const addButton = event.target.closest('[data-add-to-cart]');
        if (addButton && !addButton.disabled) {
            const card = addButton.closest('[data-product-card]');
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
                    cart.push({ productId, variantId, productName, variantName, price, quantity: 1 });
                }

                localStorage.setItem(storageKey, JSON.stringify(cart));
                refreshCart();
                showMessage(`${productName}${variantName ? ` (${variantName})` : ''} ditambahkan ke keranjang.`, 'success');
            } catch (error) {
                showMessage(error.message || 'Keranjang tidak dapat diperbarui.', 'danger');
            }
            return;
        }

        const actionButton = event.target.closest('[data-cart-action]');
        if (!actionButton || actionButton.disabled) {
            return;
        }

        try {
            const index = Number(actionButton.dataset.cartIndex);
            const cart = loadCart();
            if (!Number.isSafeInteger(index) || index < 0 || index >= cart.length) {
                throw new Error('Item keranjang tidak ditemukan. Muat ulang halaman.');
            }

            if (actionButton.dataset.cartAction === 'increase') {
                if (cart[index].quantity >= 99) {
                    showMessage('Jumlah maksimal per item adalah 99.', 'warning');
                    return;
                }
                cart[index].quantity += 1;
            } else if (actionButton.dataset.cartAction === 'decrease') {
                cart[index].quantity = Math.max(1, cart[index].quantity - 1);
            } else if (actionButton.dataset.cartAction === 'remove') {
                cart.splice(index, 1);
            } else {
                return;
            }

            localStorage.setItem(storageKey, JSON.stringify(cart));
            refreshCart();
        } catch (error) {
            showMessage(error.message || 'Keranjang tidak dapat diperbarui.', 'danger');
        }
    });

    document.querySelector('[data-checkout-form]')?.addEventListener('submit', (event) => {
        const form = event.currentTarget;
        const cartField = form.querySelector('input[name="cart_data"]');
        try {
            const cart = loadCart();
            if (cart.length === 0) {
                throw new Error('Keranjang kosong. Tambahkan menu sebelum membuat pesanan.');
            }

            cartField.value = JSON.stringify(cart.map((item) => ({
                productId: item.productId,
                variantId: item.variantId,
                quantity: item.quantity,
            })));
        } catch (error) {
            event.preventDefault();
            showMessage(error.message || 'Keranjang tidak dapat dibaca.', 'danger');
        }
    });

    document.querySelectorAll('[data-product-card]').forEach(updateDisplayedPrice);
    updateDineInTableVisibility();
    refreshCart();
})();
