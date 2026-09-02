// Данные товаров
const products = [
    { sku: "KEY-CS2-PRIME", name: "CS2 Prime Status ключ", price: 1290, oldPrice: 2000, currency: "RUB"},
    { sku: "KEY-GTA5", name: "GTA V ключ активации", price: 1990, oldPrice: 4000, currency: "RUB"},
    { sku: "SUB-DISCORD-1M", name: "Discord Nitro 1 месяц", price: 399, oldPrice: 700, currency: "RUB"},
    { sku: "KEY-GTA5", name: "GTA V ключ активации", price: 1990, oldPrice: 4000, currency: "RUB"},
    { sku: "SUB-DISCORD-1M", name: "Discord Nitro 1 месяц", price: 399, oldPrice: 700, currency: "RUB"},
];

// Инициализация карусели
let currentSlide = 0;
const totalSlides = 5;
let autoSlideInterval;

function initCarousel() {
    updateCarousel();
    startAutoSlide();
}

function updateCarousel() {
    const track = document.querySelector('.banner-carousel__track');
    track.style.transform = `translateX(-${currentSlide * 100}%)`;

    // Обновляем точки
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

catalogBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    catalogMenu.classList.toggle('active');
});

document.addEventListener('click', (e) => {
    if (!catalogMenu.contains(e.target) && !catalogBtn.contains(e.target)) {
        catalogMenu.classList.remove('active');
    }
});

// Переключатель валют
document.querySelectorAll('.currency-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.currency-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});

// Рендер карточек товаров
function renderProducts() {
    const grid = document.getElementById('productsGrid');

    products.forEach(product => {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.onclick = () => openPurchaseModal(product);

        card.innerHTML = `
            <div class="product-card__img"></div>
            <div class="product-card__content">
                <div class="product-card__name">${product.name}</div>
                <div class="product-card__prices">
                    <div class="product-card__price">${product.price} ₽</div>
                    <div class="product-card__old-price">${product.oldPrice} ₽</div>
                </div>
                <div class="product-card__button">Купить</div>
            </div>
        `;

        grid.appendChild(card);
    });
}

// Модальное окно покупки
let selectedProduct = null;

function openPurchaseModal(product) {
    selectedProduct = product;
    const modal = document.getElementById('purchaseModal');
    modal.classList.add('active');

    document.getElementById('modalProductImg').textContent = product.icon;
    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductPrice').textContent = `${product.price} ₽`;
    document.getElementById('orderEmail').value = '';
}

function closePurchaseModal() {
    document.getElementById('purchaseModal').classList.remove('active');
    selectedProduct = null;
}

// Модальное окно статуса
function closeStatusModal() {
    document.getElementById('statusModal').classList.remove('active');
}

// Покупка товара
document.getElementById('confirmPurchaseBtn').addEventListener('click', async () => {
    if (!selectedProduct) return;

    const email = document.getElementById('orderEmail').value;
    if (!email) {
        alert('Введите email');
        return;
    }

    try {
        // Создание заказа
        const createResponse = await fetch('/backend/api/create_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sku: selectedProduct.sku,
                amount: selectedProduct.price,
                email: email
            })
        });

        if (!createResponse.ok) {
            const errorData = await createResponse.json();
            throw new Error(errorData.error || 'Ошибка создания заказа');
        }

        const orderData = await createResponse.json();
        const orderId = orderData.order_id;

        console.log('Заказ создан:', orderId);

        // Эмуляция оплаты (вебхук)
        const webhookData = {
            event_id: 'evt_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
            order_id: orderId,
            status: 'paid',
            amount: selectedProduct.price,
            currency: 'RUB',
            created_at: new Date().toISOString()
        };

        console.log('Отправка вебхука:', webhookData);

        const paymentResponse = await fetch('/backend/api/webhook/payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(webhookData)
        });

        if (!paymentResponse.ok) {
            const errorData = await paymentResponse.json();
            throw new Error(errorData.error || 'Ошибка обработки платежа');
        }

        const paymentData = await paymentResponse.json();
        console.log('Результат вебхука:', paymentData);

        // Закрываем модальное окно покупки
        closePurchaseModal();

        // Показываем статус заказа
        await checkOrderStatus(orderId);

    } catch (error) {
        console.error('Ошибка при покупке:', error);
        alert('Произошла ошибка при оформлении заказа: ' + error.message);
    }
});

// Проверка статуса заказа
async function checkOrderStatus(orderId) {
    if (!orderId || orderId === 'undefined') {
        console.error('Неверный ID заказа');
        return;
    }

    const statusModal = document.getElementById('statusModal');
    const statusContent = document.getElementById('statusContent');
    statusModal.classList.add('active');

    const checkStatus = async () => {
        try {
            const response = await fetch(`/backend/api/order_status.php?order_id=${orderId}`);

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Ошибка получения статуса');
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

            // Если статус не финальный, проверяем еще раз
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

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    initCarousel();
    renderProducts();
});

// Закрытие модальных окон при клике вне
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
};