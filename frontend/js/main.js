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
    // Убираем сообщение "раскупили", если было
    const oldMessage = document.getElementById('outOfStockMessage');
    if (oldMessage) oldMessage.remove();

    // Показываем обычные элементы
    const productInfo = document.querySelector('.modal__product-info');
    if (productInfo) productInfo.style.display = 'flex';
    if (buyButton) buyButton.style.display = 'block';
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

    const buyButton = document.getElementById('confirmPurchaseBtn');
    if (buyButton) {
        buyButton.disabled = true;
        buyButton.textContent = 'Бронируем...';
    }

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
                showOutOfStockMessage(data.message || 'Товар только что раскупили');
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
        if (buyButton) {
            buyButton.textContent = 'Оплатить';
            buyButton.onclick = processPayment;
            buyButton.disabled = false;
        }

    } catch (error) {
        console.error('Ошибка бронирования:', error);
        alert('Ошибка при бронировании: ' + error.message);
        if (buyButton) {
            buyButton.disabled = false;
            buyButton.textContent = 'Забронировать';
        }
    }
}

// Показ сообщения "товар раскупили" в модальном окне
function showOutOfStockMessage(message) {
    const modalBody = document.querySelector('#purchaseModal .modal__body');
    if (!modalBody) return;

    // Скрываем обычные элементы
    const reservationBlock = document.getElementById('reservationBlock');
    const emailField = document.getElementById('emailField');
    const buyButton = document.getElementById('confirmPurchaseBtn');
    const productInfo = document.querySelector('.modal__product-info');

    if (reservationBlock) reservationBlock.style.display = 'none';
    if (emailField) emailField.style.display = 'none';
    if (buyButton) buyButton.style.display = 'none';
    if (productInfo) productInfo.style.display = 'none';

    // Убираем старое сообщение, если было
    const oldMessage = document.getElementById('outOfStockMessage');
    if (oldMessage) oldMessage.remove();

    const block = document.createElement('div');
    block.id = 'outOfStockMessage';
    block.className = 'out-of-stock-message';
    block.innerHTML = `
        <div class="out-of-stock-message__icon">😔</div>
        <p class="out-of-stock-message__text">${message}</p>
        <div class="out-of-stock-message__actions">
            <button class="out-of-stock-message__btn" id="backToCatalogBtn">К каталогу</button>
            <button class="out-of-stock-message__btn out-of-stock-message__btn--secondary" id="closeOutOfStockBtn">Закрыть</button>
        </div>
    `;

    modalBody.appendChild(block);

    document.getElementById('backToCatalogBtn').addEventListener('click', () => {
        closePurchaseModal();
    });

    document.getElementById('closeOutOfStockBtn').addEventListener('click', () => {
        closePurchaseModal();
    });
}

// Парсинг даты из формата SQLite 'YYYY-MM-DD HH:MM:SS'
function parseDateTime(dateTimeStr) {
    if (!dateTimeStr) return new Date();

    // Если строка уже содержит 'T' — это ISO, используем стандартный парсер
    if (dateTimeStr.includes('T')) {
        return new Date(dateTimeStr);
    }

    const parts = dateTimeStr.split(' ');
    const datePart = parts[0].split('-');
    const timePart = parts.length > 1 ? parts[1].split(':') : [0, 0, 0];

    return new Date(
        parseInt(datePart[0]),
        parseInt(datePart[1]) - 1,
        parseInt(datePart[2]),
        parseInt(timePart[0]),
        parseInt(timePart[1]),
        parseInt(timePart[2] || 0)
    );
}

// Показ таймера брони
function showReservationTimer(reservation) {
    if (reservationTimer) {
        clearInterval(reservationTimer);
    }

    const timerDisplay = document.getElementById('timerDisplay');
    const expiresAt = parseDateTime(reservation.expires_at).getTime();

    reservationTimer = setInterval(() => {
        const now = Date.now();
        const timeLeft = Math.max(0, expiresAt - now);

        if (timeLeft <= 0) {
            clearInterval(reservationTimer);
            if (timerDisplay) timerDisplay.textContent = 'Истекла';

            releaseReservation();

            const buyButton = document.getElementById('confirmPurchaseBtn');
            if (buyButton) {
                buyButton.disabled = false;
                buyButton.textContent = 'Забронировать';
                buyButton.onclick = createReservation;
            }

            const emailField = document.getElementById('emailField');
            if (emailField) emailField.style.display = 'none';

            // Показываем статусное сообщение вместо alert
            const timerBlock = document.getElementById('reservationTimer');
            if (timerBlock) {
                const message = document.createElement('p');
                message.className = 'reservation-expired-message';
                message.textContent = 'Бронь истекла. Товар снова доступен.';
                timerBlock.appendChild(message);
            }

            // Обновляем каталог, чтобы вернуть товар в UI
            loadProducts();
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

    const buyButton = document.getElementById('confirmPurchaseBtn');
    if (buyButton) {
        buyButton.disabled = true;
        buyButton.textContent = 'Оплачиваем...';
    }

    try {
        // Проверяем бронь
        const checkResponse = await fetch(`/backend/api/check_reservation.php?reservation_id=${currentReservation.reservation_id}`);
        const checkData = await checkResponse.json();

        if (!checkData.reservation || !checkData.reservation.is_active) {
            alert('Бронь истекла. Пожалуйста, забронируйте товар заново.');
            releaseReservation();
            if (buyButton) {
                buyButton.disabled = false;
                buyButton.textContent = 'Забронировать';
                buyButton.onclick = createReservation;
            }
            const emailField = document.getElementById('emailField');
            if (emailField) emailField.style.display = 'none';
            return;
        }

        // Генерируем intent_id один раз на попытку оплаты
        const intentId = 'intent_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

        // Создание заказа с бронью
        const createResponse = await fetch('/backend/api/create_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sku: selectedProduct.sku,
                email: email,
                reservation_id: currentReservation.reservation_id,
                intent_id: intentId
            })
        });

        const orderData = await createResponse.json();

        if (!createResponse.ok) {
            throw new Error(orderData.error || 'Ошибка создания заказа');
        }

        const orderId = orderData.order_id;

        // Сохраняем order_id для восстановления после F5
        try {
            localStorage.setItem('last_order_id', orderId);
        } catch (e) {}

        // Эмуляция оплаты. amount не шлём — сервер сам знает цену.
        const webhookData = {
            event_id: 'evt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
            order_id: orderId,
            status: 'paid',
            currency: orderData.currency || 'RUB',
            created_at: new Date().toISOString()
        };

        const paymentResponse = await fetch('/backend/api/webhook/payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(webhookData)
        });

        const paymentData = await paymentResponse.json();

        if (!paymentResponse.ok) {
            throw new Error(paymentData.error || 'Ошибка обработки платежа');
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
        if (buyButton) {
            buyButton.disabled = false;
            buyButton.textContent = 'Оплатить';
        }
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

// Восстановление статуса заказа после F5/обрыва
async function restoreLastOrder() {
    // 1. Проверяем URL: ?order=ord_xxx
    const urlParams = new URLSearchParams(window.location.search);
    let orderId = urlParams.get('order') || urlParams.get('order_id');

    // 2. Если нет в URL — берём из localStorage
    if (!orderId) {
        try {
            orderId = localStorage.getItem('last_order_id');
        } catch (e) {}
    }

    if (!orderId) return;

    // Показываем статус заказа
    await checkOrderStatus(orderId);
}

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    initCarousel();
    loadProducts();
    startLongPolling();
    restoreLastOrder();

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