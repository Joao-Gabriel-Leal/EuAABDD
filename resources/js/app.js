import './bootstrap';

const onlyDigits = (value) => value.replace(/\D/g, '');

const formatCpf = (value) => onlyDigits(value)
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');

const formatCnpj = (value) => onlyDigits(value)
    .slice(0, 14)
    .replace(/(\d{2})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');

const formatCpfOrCnpj = (value) => {
    const digits = onlyDigits(value);

    return digits.length > 11 ? formatCnpj(digits) : formatCpf(digits);
};

const formatPhone = (value) => {
    const digits = onlyDigits(value).slice(0, 11);

    if (digits.length > 10) {
        return digits
            .replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
    }

    return digits
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
};

const maskers = {
    cpf: formatCpf,
    cnpj: formatCnpj,
    'cpf-cnpj': formatCpfOrCnpj,
    phone: formatPhone,
    celular: formatPhone,
};

const applyMask = (input) => {
    const mask = input.dataset.mask;
    const masker = maskers[mask];

    if (! masker) {
        return;
    }

    input.value = masker(input.value);
};

const escapeText = (value) => {
    const span = document.createElement('span');
    span.textContent = value ?? '';

    return span.innerHTML;
};

const isoDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const monthKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;

const parseIsoDate = (value) => {
    if (! value) {
        return new Date();
    }

    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, (month || 1) - 1, day || 1);
};

const formatHumanDate = (value) => parseIsoDate(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const findCalendarSpaceSelect = (calendar) => calendar.querySelector('[data-calendar-space]')
    || calendar.closest('form')?.querySelector('[data-calendar-space]');

const setupDismissibleMessage = (message) => {
    const timeout = Number(message.dataset.dismissTimeout || 6000);
    const timer = message.querySelector('.app-message__timer');
    const closeButton = message.querySelector('[data-dismiss-message]');
    let startedAt = Date.now();
    let remaining = timeout;
    let dismissTimer = null;
    let isClosing = false;

    const setTimerDuration = (duration) => {
        if (! timer) {
            return;
        }

        timer.style.animation = 'none';
        timer.offsetHeight;
        timer.style.animation = `messageTimer ${duration}ms linear forwards`;
    };

    const close = () => {
        if (isClosing) {
            return;
        }

        isClosing = true;
        clearTimeout(dismissTimer);
        message.classList.add('is-leaving');

        setTimeout(() => {
            message.remove();
        }, 260);
    };

    const start = (duration = remaining) => {
        startedAt = Date.now();
        remaining = duration;
        clearTimeout(dismissTimer);
        setTimerDuration(remaining);
        dismissTimer = setTimeout(close, remaining);
    };

    const pause = () => {
        clearTimeout(dismissTimer);
        remaining = Math.max(0, remaining - (Date.now() - startedAt));

        if (timer) {
            timer.style.animationPlayState = 'paused';
        }
    };

    const resume = () => {
        if (isClosing) {
            return;
        }

        if (remaining <= 0) {
            close();
            return;
        }

        start(remaining);
    };

    closeButton?.addEventListener('click', close);
    message.addEventListener('mouseenter', pause);
    message.addEventListener('mouseleave', resume);
    message.addEventListener('focusin', pause);
    message.addEventListener('focusout', resume);

    start(timeout);
};

const setupCopyAndShareButtons = () => {
    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const original = button.textContent;
            const text = button.dataset.copyText || '';

            try {
                await navigator.clipboard.writeText(text);
                button.textContent = 'Copiado';
            } catch (error) {
                const fallback = document.createElement('textarea');
                fallback.value = text;
                fallback.setAttribute('readonly', '');
                fallback.style.position = 'absolute';
                fallback.style.left = '-9999px';
                document.body.appendChild(fallback);
                fallback.select();
                document.execCommand('copy');
                fallback.remove();
                button.textContent = 'Copiado';
            }

            setTimeout(() => {
                button.textContent = original;
            }, 1800);
        });
    });

    document.querySelectorAll('[data-share-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.dataset.shareText || '';

            if (navigator.share) {
                await navigator.share({ text });
                return;
            }

            await navigator.clipboard.writeText(text);
            const original = button.textContent;
            button.textContent = 'Texto copiado';
            setTimeout(() => {
                button.textContent = original;
            }, 1800);
        });
    });
};

const activateTeamTab = (workspace, tabId, updateHash = true) => {
    const target = workspace.querySelector(`[data-team-tab-target="${tabId}"]`);

    if (! target) {
        return;
    }

    workspace.querySelectorAll('[data-team-tab-target]').forEach((button) => {
        const isActive = button === target;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', String(isActive));
        button.setAttribute('tabindex', isActive ? '0' : '-1');
    });

    workspace.querySelectorAll('[data-team-tab-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.teamTabPanel !== tabId;
    });

    if (updateHash) {
        history.replaceState(null, '', `#${tabId}`);
    }
};

const setupAccessValidation = (form) => {
    const result = document.querySelector('[data-access-result]');
    const logList = document.querySelector('[data-access-log-list]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        const originalText = button?.textContent;

        if (button) {
            button.disabled = true;
            button.textContent = 'Validando...';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });
            const payload = await response.json();
            const tone = payload.tone || (payload.ok ? 'success' : 'danger');

            if (result) {
                result.hidden = false;
                result.className = `access-result access-result--${tone}`;
                result.innerHTML = `<strong>${payload.ok ? 'Liberado' : 'Bloqueado'}</strong><span>${escapeText(payload.message || 'Validacao concluida.')}</span>`;
            }

            if (payload.log && logList) {
                const row = document.createElement('div');
                row.className = 'ops-row';
                row.innerHTML = `
                    <span>${escapeText(payload.log.person_name)} <small>${escapeText(payload.log.person_type)} | ${escapeText(payload.log.gate)} | ${escapeText(payload.log.checked_at)}</small></span>
                    <strong class="${payload.log.status === 'allowed' ? 'ok' : 'warn'}">${payload.log.status_label}</strong>
                `;
                logList.prepend(row);
            }

            if (response.ok && payload.ok) {
                form.reset();
            }
        } catch (error) {
            if (result) {
                result.hidden = false;
                result.className = 'access-result access-result--danger';
                result.innerHTML = '<strong>Falha na validacao</strong><span>Nao foi possivel validar agora. O envio tradicional continua disponivel ao recarregar.</span>';
            }
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    });
};

const setupStockMovementForm = (form) => {
    const routeSelect = form.querySelector('[data-stock-product-route]');

    if (! routeSelect) {
        return;
    }

    routeSelect.addEventListener('change', () => {
        form.action = routeSelect.value;
    });

    form.action = routeSelect.value;
};

const setupReservationCalendar = (calendar) => {
    const url = calendar.dataset.availabilityUrl;
    const mode = calendar.dataset.calendarMode || 'portal';
    const form = calendar.closest('form');
    const spaceSelect = findCalendarSpaceSelect(calendar);
    const hiddenDate = form?.querySelector('[data-calendar-date-input]');
    const title = calendar.querySelector('[data-calendar-title]');
    const daysRoot = calendar.querySelector('[data-calendar-days]');
    const slotsRoot = calendar.querySelector('[data-calendar-slots]');
    const summaryRoot = calendar.querySelector('[data-calendar-summary]');
    const reservationsRoot = calendar.querySelector('[data-calendar-reservations]');
    const submit = form?.querySelector('[data-calendar-submit]');

    if (! url || ! spaceSelect || ! daysRoot || ! slotsRoot) {
        return;
    }

    let selectedDate = hiddenDate?.value || isoDate(new Date());
    let currentMonth = parseIsoDate(selectedDate);
    currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
    let calendarData = null;

    const setLoading = () => {
        title.textContent = 'Carregando...';
        daysRoot.innerHTML = '<span class="calendar-loading">Buscando agenda...</span>';
        slotsRoot.innerHTML = '';
    };

    const fetchCalendar = async () => {
        setLoading();

        const params = new URLSearchParams({
            space_id: spaceSelect.value,
            month: monthKey(currentMonth),
            date: selectedDate,
        });
        const response = await fetch(`${url}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });

        calendarData = await response.json();
        renderCalendar();
    };

    const selectDate = (date, blocked, past) => {
        if (mode === 'portal' && (blocked || past)) {
            return;
        }

        selectedDate = date;

        if (hiddenDate) {
            hiddenDate.value = date;
        }

        fetchCalendar();
    };

    const renderSlots = () => {
        slotsRoot.innerHTML = '';

        if (! calendarData?.slots?.length) {
            slotsRoot.innerHTML = '<p class="calendar-muted">Nenhum horario padrao disponivel nesta data.</p>';
            return;
        }

        calendarData.slots.forEach((slot, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'calendar-slot';
            button.textContent = slot.label;
            button.disabled = ! slot.available;

            if (slot.available && index === 0) {
                button.classList.add('is-selected');
            }

            button.addEventListener('click', () => {
                slotsRoot.querySelectorAll('.calendar-slot').forEach((slotButton) => slotButton.classList.remove('is-selected'));
                button.classList.add('is-selected');
            });

            slotsRoot.appendChild(button);
        });
    };

    const renderSummary = () => {
        if (! summaryRoot || ! calendarData) {
            return;
        }

        const available = calendarData.slots?.some((slot) => slot.available);
        const selectedText = `${formatHumanDate(selectedDate)} | ${calendarData.space.name}`;

        summaryRoot.innerHTML = available
            ? `<strong>Reserva selecionada</strong><span>${escapeText(selectedText)}</span><small>Ao confirmar, a cobranca fica vinculada ao associado.</small>`
            : `<strong>Data indisponivel</strong><span>${escapeText(selectedText)}</span><small>Escolha outro dia ou outro espaco.</small>`;

        if (submit) {
            submit.disabled = ! available;
        }
    };

    const renderReservations = () => {
        if (! reservationsRoot || ! calendarData) {
            return;
        }

        if (! calendarData.selectedReservations.length) {
            reservationsRoot.innerHTML = '<p class="calendar-muted">Nenhuma reserva ativa nesse dia.</p>';
            return;
        }

        reservationsRoot.innerHTML = calendarData.selectedReservations.map((reservation) => `
            <div class="calendar-reservation-row">
                <strong>${escapeText(reservation.member)}</strong>
                <span>${escapeText(reservation.starts_at)} as ${escapeText(reservation.ends_at)} | ${escapeText(reservation.status_label)}</span>
                <small>${escapeText(String(reservation.guests_count ?? 0))} convidados | ${escapeText(reservation.invoice || 'Sem cobranca')}</small>
            </div>
        `).join('');
    };

    const renderCalendar = () => {
        if (! calendarData) {
            return;
        }

        const monthDate = parseIsoDate(`${calendarData.month}-01`);
        title.textContent = monthDate.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        daysRoot.innerHTML = '';

        calendarData.days.forEach((day) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'calendar-day';
            button.textContent = day.day;
            button.dataset.date = day.date;
            button.classList.toggle('is-muted', ! day.currentMonth);
            button.classList.toggle('is-selected', day.date === selectedDate);
            button.classList.toggle('is-today', day.isToday);
            button.classList.toggle('is-blocked', day.isBlocked);
            button.classList.toggle('is-past', day.isPast);

            if (day.isBlocked) {
                const dot = document.createElement('span');
                dot.className = 'calendar-day__dot';
                button.appendChild(dot);
            }

            if (mode === 'portal' && (day.isBlocked || day.isPast)) {
                button.disabled = true;
            }

            button.addEventListener('click', () => selectDate(day.date, day.isBlocked, day.isPast));
            daysRoot.appendChild(button);
        });

        renderSlots();
        renderSummary();
        renderReservations();
    };

    calendar.querySelector('[data-calendar-prev]')?.addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1);
        selectedDate = isoDate(currentMonth);
        fetchCalendar();
    });

    calendar.querySelector('[data-calendar-next]')?.addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1);
        selectedDate = isoDate(currentMonth);
        fetchCalendar();
    });

    spaceSelect.addEventListener('change', fetchCalendar);

    fetchCalendar();
};

document.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.dataset.mask) {
        applyMask(event.target);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[data-mask]').forEach(applyMask);
    document.querySelectorAll('[data-dismissible-message]').forEach(setupDismissibleMessage);
    setupCopyAndShareButtons();

    document.querySelectorAll('[data-team-tabs]').forEach((workspace) => {
        const buttons = [...workspace.querySelectorAll('[data-team-tab-target]')];
        const initialTab = window.location.hash.replace('#', '') || buttons[0]?.dataset.teamTabTarget;

        activateTeamTab(workspace, initialTab, false);

        buttons.forEach((button, index) => {
            button.addEventListener('click', () => activateTeamTab(workspace, button.dataset.teamTabTarget));
            button.addEventListener('keydown', (event) => {
                if (! ['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) {
                    return;
                }

                event.preventDefault();

                const nextIndex = event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                        ? buttons.length - 1
                        : event.key === 'ArrowRight'
                            ? (index + 1) % buttons.length
                            : (index - 1 + buttons.length) % buttons.length;

                buttons[nextIndex].focus();
                activateTeamTab(workspace, buttons[nextIndex].dataset.teamTabTarget);
            });
        });
    });

    document.querySelectorAll('[data-team-tab-jump]').forEach((button) => {
        button.addEventListener('click', () => {
            const workspace = document.querySelector('[data-team-tabs]');
            const tabId = button.dataset.teamTabJump;

            if (workspace && tabId) {
                activateTeamTab(workspace, tabId);
                workspace.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    document.querySelectorAll('[data-card-flip]').forEach((card) => {
        card.addEventListener('click', () => {
            const isFlipped = card.classList.toggle('is-flipped');
            card.setAttribute('aria-pressed', String(isFlipped));
            card.querySelector('.member-card--back')?.setAttribute('aria-hidden', String(! isFlipped));
        });
    });

    document.querySelectorAll('[data-card-validation-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const token = new FormData(form).get('token')?.toString().trim();

            if (token) {
                window.location.href = `/carteirinha/validar/${encodeURIComponent(token)}`;
            }
        });
    });

    document.querySelectorAll('[data-access-validation-form]').forEach(setupAccessValidation);
    document.querySelectorAll('[data-stock-movement-form]').forEach(setupStockMovementForm);
    document.querySelectorAll('[data-reservation-calendar]').forEach(setupReservationCalendar);
});
