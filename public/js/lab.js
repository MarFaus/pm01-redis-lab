// Лаборатория: консоль redis-cli в браузере, живой список ключей и проверка заданий.
(() => {
    const root = document.querySelector('[data-lab]');
    if (!root) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const out = root.querySelector('[data-out]');
    const form = root.querySelector('[data-form]');
    const input = root.querySelector('[data-input]');
    const left = root.querySelector('[data-left]');
    const keysList = root.querySelector('[data-keys]');
    const keysNote = root.querySelector('[data-keys-note]');
    const prompt = root.querySelector('.term__prompt').textContent;

    const TYPE_LABEL = { string: 'String', hash: 'Hash', list: 'List', set: 'Set', zset: 'Sorted Set' };
    const history = [];
    let historyPos = 0;
    let scope = 'lab';
    let ttlTimer = null;

    const el = (tag, cls, text) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const scrollDown = () => { out.scrollTop = out.scrollHeight; };

    // ---------- Консоль ----------

    async function run(command) {
        const entry = el('div', 'term__entry');
        entry.append(el('div', 'term__cmd', `${prompt} ${command}`));
        const reply = el('pre', 'term__reply', '…');
        entry.append(reply);
        out.append(entry);
        scrollDown();

        try {
            const res = await fetch(root.dataset.run, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ command }),
            });
            const data = await res.json();
            reply.textContent = data.output ?? `(error) ${data.message ?? 'Неизвестная ошибка'}`;
            reply.classList.toggle('is-error', !data.ok);
            if (typeof data.left === 'number') {
                left.textContent = `Лимит: осталось ${data.left} команд в этой минуте`;
            }
        } catch {
            reply.textContent = '(error) Сервер не ответил. Проверьте, что окно start.bat открыто.';
            reply.classList.add('is-error');
        }

        scrollDown();
        loadKeys();
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const command = input.value.trim();
        if (!command) return;
        history.push(command);
        historyPos = history.length;
        input.value = '';
        run(command);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowUp' && historyPos > 0) {
            historyPos--;
            input.value = history[historyPos];
            e.preventDefault();
        } else if (e.key === 'ArrowDown') {
            historyPos = Math.min(history.length, historyPos + 1);
            input.value = history[historyPos] ?? '';
            e.preventDefault();
        }
    });

    root.querySelector('[data-clear]').addEventListener('click', () => {
        out.replaceChildren();
        input.focus();
    });

    // Клик по команде из задания: подставить в консоль. Двойной клик: сразу выполнить.
    root.querySelectorAll('[data-cmd]').forEach((btn) => {
        btn.addEventListener('click', () => {
            input.value = btn.dataset.cmd;
            input.focus();
        });
        btn.addEventListener('dblclick', () => {
            input.value = '';
            history.push(btn.dataset.cmd);
            historyPos = history.length;
            run(btn.dataset.cmd);
        });
    });

    // ---------- Ключи ----------

    function formatTtl(ttl) {
        if (ttl === -1) return 'без срока';
        if (ttl <= 0) return 'истёк';
        if (ttl < 60) return `${ttl} с`;
        const m = Math.floor(ttl / 60);
        const s = String(ttl % 60).padStart(2, '0');
        return `${m}:${s}`;
    }

    function renderKeys(data) {
        keysList.replaceChildren();

        if (!data.keys.length) {
            const empty = el('li', 'keys__empty');
            empty.textContent = scope === 'lab'
                ? 'Ключей пока нет. Выполните SET student:name "Александр", и ключ появится здесь.'
                : 'В базе сайта пусто. Откройте пару мероприятий в афише.';
            keysList.append(empty);
            return;
        }

        for (const k of data.keys) {
            const li = el('li', `key key--${k.type}`);
            const head = el('div', 'key__head');
            head.append(el('code', 'key__name', k.key));
            head.append(el('span', 'key__type', TYPE_LABEL[k.type] ?? k.type));
            const ttl = el('span', 'key__ttl', formatTtl(k.ttl));
            if (k.ttl > 0) {
                ttl.dataset.ttl = k.ttl;
                ttl.classList.add('is-ticking');
            }
            head.append(ttl);
            li.append(head);
            const size = k.size !== null ? ` [${k.size}]` : '';
            li.append(el('p', 'key__val', `${k.preview}${size}`));
            keysList.append(li);
        }

        if (data.truncated) {
            keysList.append(el('li', 'keys__empty', `Показаны первые ${data.keys.length} из ${data.total}.`));
        }
    }

    async function loadKeys() {
        try {
            const res = await fetch(`${root.dataset.keys}?scope=${scope}`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!res.ok) {
                keysList.replaceChildren(el('li', 'keys__empty', data.output ?? 'Не удалось получить ключи.'));
                return;
            }
            renderKeys(data);
        } catch {
            keysList.replaceChildren(el('li', 'keys__empty', 'Сервер не ответил.'));
        }
    }

    // TTL тикает на клиенте каждую секунду; когда ключ истекает, список перечитывается.
    function tick() {
        let expired = false;
        keysList.querySelectorAll('[data-ttl]').forEach((node) => {
            const next = Number(node.dataset.ttl) - 1;
            node.dataset.ttl = next;
            node.textContent = formatTtl(next);
            if (next <= 0) {
                node.closest('.key').classList.add('is-expired');
                expired = true;
            }
        });
        if (expired) setTimeout(loadKeys, 600);
    }

    root.querySelectorAll('[data-scope]').forEach((btn) => {
        btn.addEventListener('click', () => {
            scope = btn.dataset.scope;
            root.querySelectorAll('[data-scope]').forEach((b) => {
                b.classList.toggle('is-active', b === btn);
                b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
            });
            keysNote.innerHTML = scope === 'lab'
                ? 'Перебор через <code>SCAN</code>: в отличие от <code>KEYS *</code>, он не блокирует сервер.'
                : 'База сайта, только просмотр. Здесь просмотры, посетители, рейтинг, карточки мероприятий и лимит консоли.';
            loadKeys();
        });
    });

    // ---------- Проверка заданий ----------

    async function check(showFor) {
        try {
            const res = await fetch(root.dataset.check, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            root.querySelectorAll('[data-task]').forEach((task) => {
                const r = data[task.dataset.task];
                if (!r) return;
                task.classList.toggle('is-done', r.done);
                if (showFor === task.dataset.task) {
                    const p = task.querySelector('[data-result]');
                    p.textContent = (r.done ? 'Готово. ' : 'Пока нет. ') + r.detail;
                    p.classList.toggle('is-done', r.done);
                }
            });
        } catch {
            // Проверка необязательна: без неё консоль продолжает работать.
        }
    }

    root.querySelectorAll('[data-check-btn]').forEach((btn) => {
        btn.addEventListener('click', () => check(btn.closest('[data-task]').dataset.task));
    });

    loadKeys();
    check(null);
    ttlTimer = setInterval(tick, 1000);
})();
