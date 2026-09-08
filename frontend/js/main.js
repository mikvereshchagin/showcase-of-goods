// Данные товаров (загружаются с сервера)
let products = [];

// Инициализация карусели
let currentSlide = 0;
const totalSlides = 5;
let autoSlideInterval;
let selectedProduct = null;
let currentReservation = null;
let reservationTimer = null;
let lastUpdateTimestamp = 0;
let longPollingActive = true;

function initCarousel() {
    updateCarousel();
    startAutoSlide();
}

function updateCarousel() {
    const track = document.querySelector('.banner-carousel__track');
    if (track) {
        track.style.transform = `translateX(-${currentSlide * 100}%)`;
    }

    document.querySelectorAll('.banner-carousel__dot').forEach((dot, index) => {
        dot.classList.toggle('active', index === currentSlide);
    });
}

function changeSlide(direction) {
    currentSlide = (currentSlide + direction + totalSlides) % totalSlides;
    updateCarousel();
    restartAutoSlide();
}

function goToSlide(index) {
    currentSlide = index;
    updateCarousel();
    restartAutoSlide();
}

function startAutoSlide() {
    autoSlideInterval = setInterval(() => {
        changeSlide(1);
    }, 5000);
}

function restartAutoSlide() {
    clearInterval(autoSlideInterval);
    startAutoSlide();
}

// Каталог меню
const catalogBtn = document.getElementById('catalogBtn');
const catalogMenu = document.getElementById('catalogMenu');

if (catalogBtn && catalogMenu) {
    catalogBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        catalogMenu.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
        if (!catalogMenu.contains(e.target) && !catalogBtn.contains(e.target)) {
            catalogMenu.classList.remove('active');
        }
    });
}

// Переключатель валют
document.querySelectorAll('.currency-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.currency-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});

// Загрузка товаров с сервера
async function loadProducts() {
    try {
        const response = await fetch('/backend/api/products.php');
        if (!response.ok) {
            throw new Error('Ошибка загрузки товаров');
        }

        const data = await response.json();
        if (data.products && data.products.length > 0) {
            products = data.products;
        }

        renderProducts();
        lastUpdateTimestamp = data.timestamp || Date.now();

    } catch (error) {
        console.error('Ошибка загрузки товаров:', error);
        renderProducts();
    }
}

// Long polling для обновлений в реальном времени
async function startLongPolling() {
    while (longPollingActive) {
        try {
            const response = await fetch(`/backend/api/inventory_updates.php?last_update=${lastUpdateTimestamp}`);

            if (!response.ok) {
                throw new Error('Ошибка long polling');
            }

            const data = await response.json();

            if (data.has_updates && data.products && data.products.length > 0) {
                products = data.products;
                lastUpdateTimestamp = data.timestamp;
                renderProducts();
                console.log('Товары обновлены:', new Date().toLocaleTimeString());
            }

        } catch (error) {
            console.error('Ошибка long polling:', error);
            await new Promise(resolve => setTimeout(resolve, 3000));
        }
    }
}

// Рендер карточек товаров
function renderProducts() {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;

    grid.innerHTML = '';

    if (products.length === 0) {
        grid.innerHTML = '<p style="color: #fff;">Нет доступных товаров</p>';
        return;
    }

    products.forEach(product => {
        const card = document.createElement('div');
        card.className = 'product-card';

        if (product.stock <= 0) {
            card.classList.add('product-card--out-of-stock');
        }

        card.onclick = () => {
            if (product.stock > 0) {
                openPurchaseModal(product);
            }
        };

        const stockBadge = product.stock <= 0
            ? '<div class="product-card__out-badge">Нет в наличии</div>'
            : product.stock <= 3
                ? `<div class="product-card__low-stock">Осталось: ${product.stock}</div>`
                : '';

        card.innerHTML = `
            ${stockBadge}
            <div class="product-card__img"></div>
            <div class="product-card__content">
                <div class="product-card__name">${product.name}</div>
                <div class="product-card__prices">
                    <div class="product-card__price">${product.price} ₽</div>
                    <div class="product-card__old-price">${product.old_price} ₽</div>
                </div>
                <div class="product-card__stock">В наличии: ${product.stock}</div>
                <div class="product-card__button">${product.stock > 0 ? 'Купить' : 'Нет в наличии'}</div>
            </div>
        `;

        grid.appendChild(card);
    });
}

// Модальное окно покупки
function openPurchaseModal(product) {
    selectedProduct = product;
    const modal = document.getElementById('purchaseModal');
    if (!modal) return;

    modal.classList.add('active');

    document.getElementById('modalProductImg').textContent = '🎮';
    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductPrice').textContent = `${product.price} ${product.currency || 'RUB'}`;
    document.getElementById('orderEmail').value = '';

    // Показываем блок бронирования
    const reservationBlock = document.getElementById('reservationBlock');
    if (reservationBlock) reservationBlock.style.display = 'block';

    // Скрываем email поле
    const emailField = document.getElementById('emailField');
    if (emailField) emailField.style.display = 'none';

    // Скрываем таймер
    const timerBlock = document.getElementById('reservationTimer');
    if (timerBlock) timerBlock.style.display = 'none';

    // Устанавливаем кнопку
    const buyButton = document.getElementById('confirmPurchaseBtn');
    if (buyButton) {
        buyButton.textContent = 'Забронировать';
        buyButton.onclick = createReservation;
    }
}

function closePurchaseModal() {
    const modal = document.getElementById('purchaseModal');
    if (modal) modal.classList.remove('active');

    if (currentReservation) {
        releaseReservation();
    }

    selectedProduct = null;
    currentReservation = null;

    if (reservationTimer) {
        clearInterval(reservationTimer);
        reservationTimer = null;
    }
}

// Создание брони
async function createReservation() {
    if (!selectedProduct) return;

    try {
        const response = await fetch('/backend/api/reserve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sku: selectedProduct.sku
            })
        });

        const data = await response.json();

        if (!response.ok) {
            if (response.status === 409) {
                alert(data.message || 'Товар только что раскупили');
                closePurchaseModal();
                loadProducts();
            } else {
                throw new Error(data.error || 'Ошибка бронирования');
            }
            return;
        }

        currentReservation = data;

        // Показываем таймер
        const timerBlock = document.getElementById('reservationTimer');
        if (timerBlock) timerBlock.style.display = 'block';

        showReservationTimer(data);

        // Показываем email поле
        const emailField = document.getElementById('emailField');
        if (emailField) emailField.style.display = 'block';

        // Меняем кнопку
        const buyButton = document.getElementById('confirmPurchaseBtn');
        if (buyButton) {
            buyButton.textContent = 'Оплатить';
            buyButton.onclick = processPayment;
        }

    } catch (error) {
        console.error('Ошибка бронирования:', error);
        alert('Ошибка при бронировании: ' + error.message);
    }
}

// Показ таймера брони
function showReservationTimer(reservation) {
    if (reservationTimer) {
        clearInterval(reservationTimer);
    }

    const timerDisplay = document.getElementById('timerDisplay');
    const expiresAt = new Date(reservation.expires_at).getTime();

    reservationTimer = setInterval(() => {
        const now = Date.now();
        const timeLeft = Math.max(0, expiresAt - now);

        if (timeLeft <= 0) {
            clearInterval(reservationTimer);
            if (timerDisplay) timerDisplay.textContent = 'Истекла';

            releaseReservation();

            const buyButton = document.getElementById('confirmPurchaseBtn');
            if (buyButton) {
                buyButton.textContent = 'Забронировать';
                buyButton.onclick = createReservation;
            }

            const emailField = document.getElementById('emailField');
            if (emailField) emailField.style.display = 'none';

            alert('Время брони истекло. Товар снова доступен.');
            return;
        }

        const seconds = Math.floor(timeLeft / 1000);
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;

        if (timerDisplay) {
            timerDisplay.textContent = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }
    }, 1000);
}

// Снятие брони
async function releaseReservation() {
    if (!currentReservation) return;

    try {
        await fetch('/backend/api/release_reservation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                reservation_id: currentReservation.reservation_id
            })
        });
    } catch (error) {
        console.error('Ошибка снятия брони:', error);
    }

    currentReservation = null;
}

// Обработка оплаты
async function processPayment() {
    if (!selectedProduct || !currentReservation) return;

    const emailInput = document.getElementById('orderEmail');
    const email = emailInput ? emailInput.value : '';

    if (!email) {
        alert('Введите email');
        return;
    }

    try {
        // Проверяем бронь
        const checkResponse = await fetch(`/backend/api/check_reservation.php?reservation_id=${currentReservation.reservation_id}`);
        const checkData = await checkResponse.json();

        if (!checkData.reservation || !checkData.reservation.is_active) {
            alert('Бронь истекла. Пожалуйста, забронируйте товар заново.');
            releaseReservation();
            return;
        }

        // Создание заказа с бронью
        const createResponse = await fetch('/backend/api/create_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sku: selectedProduct.sku,
                amount: selectedProduct.price,
                email: email,
                reservation_id: currentReservation.reservation_id
            })
        });

        const orderData = await createResponse.json();

        if (!createResponse.ok) {
            throw new Error(orderData.error || 'Ошибка создания заказа');
        }

        const orderId = orderData.order_id;

        // Эмуляция оплаты
        const webhookData = {
            event_id: 'evt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
            order_id: orderId,
            status: 'paid',
            amount: selectedProduct.price,
            currency: selectedProduct.currency || 'RUB',
            created_at: new Date().toISOString()
        };

        const paymentResponse = await fetch('/backend/api/webhook/payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(webhookData)
        });

        if (!paymentResponse.ok) {
            const errorData = await paymentResponse.json();
            throw new Error(errorData.error || 'Ошибка обработки платежа');
        }

        // Очищаем таймер
        if (reservationTimer) {
            clearInterval(reservationTimer);
        }

        closePurchaseModal();
        await checkOrderStatus(orderId);
        loadProducts();

    } catch (error) {
        console.error('Ошибка при покупке:', error);
        alert('Ошибка при оформлении заказа: ' + error.message);
    }
}

// Проверка статуса заказа
async function checkOrderStatus(orderId) {
    if (!orderId || orderId === 'undefined') return;

    const statusModal = document.getElementById('statusModal');
    const statusContent = document.getElementById('statusContent');
    if (!statusModal || !statusContent) return;

    statusModal.classList.add('active');

    const checkStatus = async () => {
        try {
            const response = await fetch(`/backend/api/order_status.php?order_id=${orderId}`);

            if (!response.ok) {
                throw new Error('Ошибка получения статуса');
            }

            const data = await response.json();

            const statusLabels = {
                'created': 'Создан',
                'paid': 'Оплачен',
                'delivering': 'Выдается',
                'delivered': 'Доставлен',
                'out_of_stock': 'Нет в наличии',
                'delivery_failed': 'Ошибка выдачи'
            };

            let html = `
                <p><strong>Номер заказа:</strong> ${orderId}</p>
                <p><strong>Статус:</strong> ${statusLabels[data.status] || data.status}</p>
            `;

            if (data.status === 'delivered' && data.delivery_code) {
                html += `
                    <div class="key-content">
                        <p><strong>Ваш ключ:</strong></p>
                        <p class="key">${data.delivery_code}</p>
                    </div>
                `;
            }

            statusContent.innerHTML = html;

            if (!['delivered', 'out_of_stock', 'delivery_failed'].includes(data.status)) {
                setTimeout(checkStatus, 2000);
            }
        } catch (error) {
            console.error('Ошибка проверки статуса:', error);
            statusContent.innerHTML = `<p>Ошибка: ${error.message}</p>`;
        }
    };

    await checkStatus();
}

function closeStatusModal() {
    const modal = document.getElementById('statusModal');
    if (modal) modal.classList.remove('active');
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    initCarousel();
    loadProducts();
    startLongPolling();

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            if (event.target.id === 'purchaseModal') {
                closePurchaseModal();
            } else if (event.target.id === 'statusModal') {
                closeStatusModal();
            }
        }
    };

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const purchaseModal = document.getElementById('purchaseModal');
            const statusModal = document.getElementById('statusModal');

            if (purchaseModal && purchaseModal.classList.contains('active')) {
                closePurchaseModal();
            }
            if (statusModal && statusModal.classList.contains('active')) {
                closeStatusModal();
            }
        }
    });

    window.addEventListener('beforeunload', () => {
        longPollingActive = false;
        if (currentReservation) {
            releaseReservation();
        }
    });
});