// Обратный отсчёт TTL кеша в «чеке запроса» на афише.
(() => {
    const node = document.querySelector('[data-countdown]');
    if (!node || node.dataset.countdown === '') return;

    let left = Number(node.dataset.countdown);
    const timer = setInterval(() => {
        left--;
        if (left <= 0) {
            node.textContent = 'истёк, обновите страницу';
            clearInterval(timer);
            return;
        }
        node.textContent = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
    }, 1000);
})();
