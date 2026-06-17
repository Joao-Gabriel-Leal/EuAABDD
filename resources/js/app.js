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

const formatCurrency = (value) => Number(value || 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
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

const tabWorkspaceConfig = (workspace) => {
    if (workspace.hasAttribute('data-tab-workspace')) {
        return {
            buttonSelector: '[data-tab-target]',
            panelSelector: '[data-tab-panel]',
            targetKey: 'tabTarget',
            panelKey: 'tabPanel',
        };
    }

    return {
        buttonSelector: '[data-team-tab-target]',
        panelSelector: '[data-team-tab-panel]',
        targetKey: 'teamTabTarget',
        panelKey: 'teamTabPanel',
    };
};

const activateWorkspaceTab = (workspace, tabId, updateHash = true) => {
    const config = tabWorkspaceConfig(workspace);
    const buttons = [...workspace.querySelectorAll(config.buttonSelector)];
    const target = buttons.find((button) => button.dataset[config.targetKey] === tabId) || buttons[0];

    if (! target) {
        return;
    }

    const activeTab = target.dataset[config.targetKey];

    buttons.forEach((button) => {
        const isActive = button === target;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', String(isActive));
        button.setAttribute('tabindex', isActive ? '0' : '-1');
    });

    workspace.querySelectorAll(config.panelSelector).forEach((panel) => {
        panel.hidden = panel.dataset[config.panelKey] !== activeTab;
    });

    if (updateHash) {
        history.replaceState(null, '', `#${activeTab}`);
    }
};

const setupTabWorkspace = (workspace) => {
    const config = tabWorkspaceConfig(workspace);
    const buttons = [...workspace.querySelectorAll(config.buttonSelector)];
    const hashTab = window.location.hash.replace('#', '');
    const hashMatches = buttons.some((button) => button.dataset[config.targetKey] === hashTab);
    const initialTab = hashMatches
        ? hashTab
        : workspace.dataset.defaultTab || buttons[0]?.dataset[config.targetKey];

    activateWorkspaceTab(workspace, initialTab, false);

    buttons.forEach((button, index) => {
        button.addEventListener('click', () => activateWorkspaceTab(workspace, button.dataset[config.targetKey]));
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
            activateWorkspaceTab(workspace, buttons[nextIndex].dataset[config.targetKey]);
        });
    });
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
        reservationsRoot && (reservationsRoot.innerHTML = '');
        summaryRoot && (summaryRoot.innerHTML = '');
    };

    const setCalendarError = () => {
        title.textContent = 'Agenda indisponivel';
        daysRoot.innerHTML = '<span class="calendar-loading calendar-loading--error">Nao foi possivel carregar a agenda. Recarregue a pagina ou tente outro espaco.</span>';
        slotsRoot.innerHTML = '<p class="calendar-muted">A disponibilidade nao retornou agora.</p>';

        if (summaryRoot) {
            summaryRoot.innerHTML = '<strong>Agenda nao carregada</strong><span>O restante da tela continua disponivel para editar espacos e pins.</span>';
        }

        if (reservationsRoot) {
            reservationsRoot.innerHTML = '';
        }

        if (submit) {
            submit.disabled = true;
        }
    };

    const markSelectedDay = () => {
        daysRoot.querySelectorAll('.calendar-day').forEach((button) => {
            button.classList.toggle('is-selected', button.dataset.date === selectedDate);
        });
    };

    const fetchCalendar = async (showLoading = true) => {
        if (showLoading) {
            setLoading();
        }

        const params = new URLSearchParams({
            space_id: spaceSelect.value,
            month: monthKey(currentMonth),
            date: selectedDate,
        });
        try {
            const response = await fetch(`${url}?${params.toString()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (! response.ok) {
                throw new Error(`Agenda indisponivel (${response.status})`);
            }

            calendarData = await response.json();
            renderCalendar();
        } catch (error) {
            console.warn(error);
            calendarData = null;
            setCalendarError();
        }
    };

    const selectDate = (date, blocked, past) => {
        if (mode === 'portal' && (blocked || past)) {
            return;
        }

        selectedDate = date;

        if (hiddenDate) {
            hiddenDate.value = date;
        }

        markSelectedDay();
        fetchCalendar(false);
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

const setupReservationBuilder = (form) => {
    const spaceSelect = form.querySelector('[data-calendar-space]');
    const pins = [...form.querySelectorAll('[data-space-map-pin]')];
    const typeFilters = [...form.querySelectorAll('[data-space-type-filter]')];
    const guestList = form.querySelector('[data-reservation-guest-list]');
    const addGuestButton = form.querySelector('[data-add-reservation-guest]');
    const paymentModes = [...form.querySelectorAll('[data-payment-mode]')];
    const summaryBase = form.querySelector('[data-summary-base]');
    const summaryGuests = form.querySelector('[data-summary-guests]');
    const summaryMember = form.querySelector('[data-summary-member]');
    const summarySplit = form.querySelector('[data-summary-split]');

    if (! spaceSelect) {
        return;
    }

    let selectedTypeFilter = 'all';

    const selectedOption = () => spaceSelect.selectedOptions[0];

    const selectedPaymentMode = () => paymentModes.find((input) => input.checked)?.value || 'associado_paga';

    const guestRows = () => guestList ? [...guestList.querySelectorAll('[data-reservation-guest-row]')] : [];

    const guestsWithName = () => guestRows().filter((row) => row.querySelector('[data-guest-name]')?.value.trim());

    const reindexGuests = () => {
        if (! guestList) {
            return;
        }

        guestRows().forEach((row, index) => {
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/guests\[\d+]/, `guests[${index}]`);
            });
        });
    };

    const updateEmailRequirements = () => {
        if (! guestList) {
            return;
        }

        const isSplit = selectedPaymentMode() === 'rateio_email';

        guestRows().forEach((row) => {
            const name = row.querySelector('[data-guest-name]')?.value.trim();
            const email = row.querySelector('[data-guest-email]');

            if (email) {
                email.required = isSplit && Boolean(name);
            }
        });
    };

    const updatePins = () => {
        const selectedId = spaceSelect.value;

        pins.forEach((pin) => {
            const matchesFilter = selectedTypeFilter === 'all' || pin.dataset.spaceType === selectedTypeFilter;

            pin.hidden = ! matchesFilter;
            pin.classList.toggle('is-active', pin.dataset.spaceId === selectedId);
        });
    };

    const updateTypeFilters = () => {
        typeFilters.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.spaceTypeFilter === selectedTypeFilter);
        });
    };

    const firstVisibleSpaceId = () => [...spaceSelect.options]
        .find((option) => selectedTypeFilter === 'all' || option.dataset.spaceType === selectedTypeFilter)
        ?.value;

    const ensureSelectedSpaceVisible = () => {
        const option = selectedOption();
        const isVisible = selectedTypeFilter === 'all' || option?.dataset.spaceType === selectedTypeFilter;

        if (isVisible) {
            return;
        }

        const nextId = firstVisibleSpaceId();

        if (nextId) {
            spaceSelect.value = nextId;
            spaceSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    const updateSummary = () => {
        ensureSelectedSpaceVisible();
        const option = selectedOption();
        const basePrice = Number(option?.dataset.basePrice || 0);
        const guestPrice = Number(option?.dataset.guestPrice || 14);
        const guestCount = guestsWithName().length;
        const guestTotal = guestCount * guestPrice;
        const memberTotal = selectedPaymentMode() === 'associado_paga'
            ? basePrice + guestTotal
            : basePrice;
        const splitTotal = selectedPaymentMode() === 'rateio_email' ? guestTotal : 0;

        if (summaryBase) {
            summaryBase.textContent = formatCurrency(basePrice);
        }

        if (summaryGuests) {
            summaryGuests.textContent = `${guestCount} x ${formatCurrency(guestPrice)}`;
        }

        if (summaryMember) {
            summaryMember.textContent = `Total ${formatCurrency(memberTotal)}`;
        }

        if (summarySplit) {
            summarySplit.textContent = formatCurrency(splitTotal);
        }

        updateEmailRequirements();
        updateTypeFilters();
        updatePins();
    };

    const makeGuestRow = () => {
        if (! guestList) {
            return null;
        }

        const index = guestRows().length;
        const row = document.createElement('div');
        row.className = 'reservation-guest-row';
        row.dataset.reservationGuestRow = '';
        row.innerHTML = `
            <input name="guests[${index}][name]" data-guest-name placeholder="Nome do convidado">
            <input name="guests[${index}][cpf]" data-mask="cpf" inputmode="numeric" maxlength="14" placeholder="CPF opcional">
            <input name="guests[${index}][email]" type="email" data-guest-email placeholder="E-mail para rateio">
            <button class="mini-button mini-button--light" type="button" data-remove-reservation-guest>Remover</button>
        `;

        row.querySelectorAll('input').forEach((input) => {
            input.addEventListener('input', () => {
                if (input.dataset.mask) {
                    applyMask(input);
                }

                updateSummary();
            });
        });

        row.querySelector('[data-remove-reservation-guest]')?.addEventListener('click', () => {
            if (guestRows().length === 1) {
                row.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });
            } else {
                row.remove();
            }

            reindexGuests();
            updateSummary();
        });

        return row;
    };

    guestRows().forEach((row) => {
        row.querySelectorAll('input').forEach((input) => input.addEventListener('input', updateSummary));
        row.querySelector('[data-remove-reservation-guest]')?.addEventListener('click', () => {
            if (guestRows().length === 1) {
                row.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });
            } else {
                row.remove();
            }

            reindexGuests();
            updateSummary();
        });
    });

    addGuestButton?.addEventListener('click', () => {
        const row = makeGuestRow();

        if (! row) {
            return;
        }

        guestList.appendChild(row);
        reindexGuests();
        updateSummary();
    });

    pins.forEach((pin) => {
        pin.addEventListener('click', () => {
            if (! pin.dataset.spaceId || pin.hidden) {
                return;
            }

            spaceSelect.value = pin.dataset.spaceId;
            spaceSelect.dispatchEvent(new Event('change', { bubbles: true }));
            updateSummary();
        });
    });

    typeFilters.forEach((button) => {
        button.addEventListener('click', () => {
            selectedTypeFilter = button.dataset.spaceTypeFilter || 'all';
            updateSummary();
        });
    });

    spaceSelect.addEventListener('change', updateSummary);
    paymentModes.forEach((input) => input.addEventListener('change', updateSummary));
    updateSummary();
};

const setupPastReservationsToggle = () => {
    document.querySelectorAll('[data-toggle-past-reservations]').forEach((button) => {
        const container = button.closest('.reservation-history');
        const target = container?.querySelector('[data-past-reservations]');

        if (! target) {
            return;
        }

        button.addEventListener('click', () => {
            const shouldShow = target.hidden;
            target.hidden = ! shouldShow;
            button.textContent = shouldShow ? 'Ocultar passadas' : 'Ver passadas';
        });
    });
};

const setupReservationModals = () => {
    const modals = [...document.querySelectorAll('[data-reservation-modal]')];
    let activeModal = null;

    modals.forEach((modal) => {
        document.body.appendChild(modal);
    });

    const closeModal = () => {
        if (! activeModal) {
            return;
        }

        activeModal.hidden = true;
        activeModal = null;
        document.body.classList.remove('modal-open');
    };

    const openModal = (id) => {
        const modal = modals.find((candidate) => candidate.dataset.reservationModal === id);

        if (! modal) {
            return;
        }

        if (activeModal && activeModal !== modal) {
            activeModal.hidden = true;
        }

        activeModal = modal;
        modal.hidden = false;
        document.body.classList.add('modal-open');
        modal.querySelector('[data-close-reservation-modal]')?.focus();
    };

    document.querySelectorAll('[data-open-reservation-modal]').forEach((button) => {
        button.addEventListener('click', () => openModal(button.dataset.openReservationModal));
    });

    modals.forEach((modal) => {
        modal.querySelectorAll('[data-close-reservation-modal]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
};

const setupMapCoordinatePicker = (picker) => {
    const canvas = picker.querySelector('.map-pin-editor__canvas');
    const pin = picker.querySelector('[data-map-picker-pin]');
    const form = picker.closest('form');
    const inputX = form?.querySelector('[data-map-x-input]');
    const inputY = form?.querySelector('[data-map-y-input]');
    const typeSelect = form?.querySelector('[data-space-type-select]');
    const label = picker.querySelector('[data-map-position-label]');

    if (! canvas || ! pin || ! inputX || ! inputY) {
        return;
    }

    const clampPercent = (value) => Math.max(0, Math.min(100, Math.round(value)));

    const movePin = (x, y) => {
        const nextX = clampPercent(x);
        const nextY = clampPercent(y);

        inputX.value = String(nextX);
        inputY.value = String(nextY);
        pin.style.left = `${nextX}%`;
        pin.style.top = `${nextY}%`;

        if (label) {
            label.textContent = `${nextX}%, ${nextY}%`;
        }
    };

    const updatePinColor = () => {
        const color = typeSelect?.selectedOptions?.[0]?.dataset.pinColor;

        if (color) {
            pin.style.setProperty('--pin-color', color);
        }
    };

    canvas.addEventListener('click', (event) => {
        const rect = canvas.getBoundingClientRect();
        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;

        movePin(x, y);
    });

    typeSelect?.addEventListener('change', updatePinColor);
    updatePinColor();
    movePin(Number(inputX.value || 50), Number(inputY.value || 50));
};

const setupSpaceTypeColorForm = (form) => {
    const input = form.querySelector('[data-pin-color-input]');
    const preview = form.querySelector('[data-pin-preview]');

    if (! input || ! preview) {
        return;
    }

    const updatePreview = () => {
        preview.style.setProperty('--pin-color', input.value || '#e5163d');
    };

    form.querySelectorAll('[data-pin-color-choice]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.pinColorChoice || '#e5163d';
            updatePreview();
        });
    });

    input.addEventListener('input', updatePreview);
    updatePreview();
};

const setupTeamSpaceMap = (section) => {
    const pins = [...section.querySelectorAll('[data-team-space-pin]')];
    const listItems = [...section.querySelectorAll('[data-team-space-list-item]')];
    const details = [...section.querySelectorAll('[data-team-space-detail]')];
    const empty = section.querySelector('[data-team-space-empty]');

    if (! pins.length || ! details.length) {
        return;
    }

    const selectSpace = (spaceId) => {
        pins.forEach((pin) => {
            pin.classList.toggle('is-active', pin.dataset.spaceId === spaceId);
        });

        listItems.forEach((item) => {
            item.classList.toggle('is-active', item.dataset.spaceId === spaceId);
        });

        details.forEach((detail) => {
            detail.hidden = detail.dataset.teamSpaceDetail !== spaceId;
        });

        if (empty) {
            empty.hidden = true;
        }
    };

    pins.forEach((pin) => {
        pin.addEventListener('click', () => {
            if (pin.dataset.spaceId) {
                selectSpace(pin.dataset.spaceId);
            }
        });
    });

    listItems.forEach((item) => {
        item.addEventListener('click', () => {
            if (item.dataset.spaceId) {
                selectSpace(item.dataset.spaceId);
            }
        });
    });

    const firstSpaceId = pins[0]?.dataset.spaceId || listItems[0]?.dataset.spaceId;

    if (firstSpaceId) {
        selectSpace(firstSpaceId);
    }
};

const compressReservationMapFile = (file) => new Promise((resolve, reject) => {
    const image = new Image();
    const url = URL.createObjectURL(file);

    image.onload = () => {
        URL.revokeObjectURL(url);

        const maxSize = 2200;
        const scale = Math.min(1, maxSize / Math.max(image.width, image.height));
        const width = Math.max(1, Math.round(image.width * scale));
        const height = Math.max(1, Math.round(image.height * scale));
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');

        canvas.width = width;
        canvas.height = height;

        if (! context) {
            reject(new Error('Canvas indisponivel para comprimir a planta.'));
            return;
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        context.drawImage(image, 0, 0, width, height);

        canvas.toBlob((blob) => {
            if (! blob) {
                reject(new Error('Nao foi possivel comprimir a planta.'));
                return;
            }

            const compressedFile = new File(
                [blob],
                file.name.replace(/\.[^.]+$/, '') + '.jpg',
                { type: 'image/jpeg', lastModified: Date.now() },
            );

            resolve(compressedFile.size < file.size ? compressedFile : file);
        }, 'image/jpeg', 0.86);
    };

    image.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Nao foi possivel ler a imagem da planta.'));
    };

    image.src = url;
});

const setupReservationMapUpload = (section) => {
    const form = section.querySelector('form');
    const input = form?.querySelector('input[name="reservation_map"]');
    const button = form?.querySelector('button[type="submit"]');
    let shouldSubmitNative = false;

    if (! form || ! input) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        if (shouldSubmitNative) {
            shouldSubmitNative = false;
            return;
        }

        const file = input.files?.[0];

        if (! file || file.size <= 1900 * 1024 || ! file.type.startsWith('image/')) {
            return;
        }

        event.preventDefault();

        const originalText = button?.textContent;

        if (button) {
            button.disabled = true;
            button.textContent = 'Otimizando imagem...';
        }

        try {
            const compressedFile = await compressReservationMapFile(file);
            const transfer = new DataTransfer();

            transfer.items.add(compressedFile);
            input.files = transfer.files;
        } catch (error) {
            console.warn(error);
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }

            shouldSubmitNative = true;
            form.requestSubmit();
        }
    });
};

const setupPlanSelectionButtons = () => {
    const planSelect = document.querySelector('[data-plan-target]');

    if (! planSelect) {
        return;
    }

    document.querySelectorAll('[data-plan-select]').forEach((button) => {
        button.addEventListener('click', () => {
            const planId = button.dataset.planSelect;

            if (! planId) {
                return;
            }

            planSelect.value = planId;
            planSelect.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
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
    setupPlanSelectionButtons();

    document.querySelectorAll('[data-team-tabs], [data-tab-workspace]').forEach(setupTabWorkspace);

    document.querySelectorAll('[data-team-tab-jump]').forEach((button) => {
        button.addEventListener('click', () => {
            const workspace = document.querySelector('[data-team-tabs]');
            const tabId = button.dataset.teamTabJump;

            if (workspace && tabId) {
                activateWorkspaceTab(workspace, tabId);
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
    document.querySelectorAll('[data-map-picker]').forEach(setupMapCoordinatePicker);
    document.querySelectorAll('[data-space-type-form]').forEach(setupSpaceTypeColorForm);
    document.querySelectorAll('[data-team-space-map]').forEach(setupTeamSpaceMap);
    document.querySelectorAll('[data-reservation-map-upload]').forEach(setupReservationMapUpload);
    document.querySelectorAll('[data-reservation-builder]').forEach(setupReservationBuilder);
    document.querySelectorAll('[data-reservation-calendar]').forEach(setupReservationCalendar);
    setupPastReservationsToggle();
    setupReservationModals();
});
