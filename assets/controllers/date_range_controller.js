import { Controller } from '@hotwired/stimulus';

// vanilla-calendar-pro is loaded when the first date field is on the page, not with the bundle: an application
// without one does not need it.
let calendarModule = null;

function loadCalendar() {
    calendarModule ??= import('vanilla-calendar-pro');

    return calendarModule;
}

/*
 * A date field, or a field of two dates "from - to", picked in a calendar that opens below it (vanilla-calendar-pro):
 * two months side by side for a range, the days between the two highlighted. The field shows the dates in words
 * ("Wed, Nov 11 — Sun, Nov 15"); the dates themselves go to hidden inputs, with an "input" event, so a data-model on
 * them works as on any input. With data-date-range-time-value="true" the calendar has a time below it too (from and
 * to for a range) and the values are a date and time (2026-11-11T14:30).
 *
 * <div data-controller="date-range" data-date-range-range-value="true" data-date-range-locale-value="pl"
 *      data-date-range-from-value="2026-11-11" data-date-range-to-value="2026-11-15"
 *      data-date-range-labels-value='{"from": "From", "to": "To", "time": "Time", "dialog": "Added"}'>
 *     <input type="hidden" data-date-range-target="from" data-model="filters.createdAt.value" value="2026-11-11">
 *     <input type="hidden" data-date-range-target="to" data-model="filters.createdAt.to" value="2026-11-15">
 *     <input type="text" readonly id="created-at-dates" data-live-ignore data-date-range-target="display"
 *            data-action="click->date-range#toggle keydown->date-range#key">
 *     <button type="button" data-date-range-target="clear" data-action="date-range#clear">×</button>
 * </div>
 *
 * The from/to values follow what the server renders (a live re-render, a cleared filter): the field shows them, the
 * calendar opens on them. The range value may change in the browser (the comparison controller). The display input keeps what this controller wrote (data-live-ignore, with an id). The
 * calendar is in a box of its own at the end of the page (of the dialog the field is in), out of the re-rendered part.
 *
 * The week starts on the first day of the locale (Intl.Locale weekInfo, where the browser knows it; else Monday), or on
 * the one of data-date-range-first-weekday-value (0 Sunday … 6 Saturday). A range shows two months side by side from
 * data-date-range-breakpoint-value pixels of width up (640). The texts of the labels value fall back to English.
 */
export default class extends Controller {
    static targets = ['from', 'to', 'display', 'clear'];
    static values = {
        range: Boolean,
        time: Boolean,
        from: String,
        to: String,
        locale: String,
        placeholder: String,
        labels: Object,
        firstWeekday: Number,
        breakpoint: { type: Number, default: 640 },
    };

    connect() {
        this.connected = true;
        this.refresh();
        loadCalendar().catch(() => {
            // Told when the calendar is opened.
        });
    }

    disconnect() {
        this.connected = false;
        this.close(false);
    }

    // What the server rendered. The controller does not write its own attributes back: the live component would keep
    // that change and lay it over what a later render says (filters cleared on the server).
    fromValueChanged() {
        this.from = this.fromValue;
        this.refresh();
    }

    toValueChanged() {
        this.to = this.toValue;
        this.refresh();
    }

    rangeValueChanged() {
        this.reopen();
    }

    timeValueChanged() {
        this.reopen();
    }

    placeholderValueChanged() {
        this.refresh();
    }

    toggle() {
        this.popup ? this.close() : this.open();
    }

    key(event) {
        if (['Enter', ' ', 'ArrowDown'].includes(event.key)) {
            event.preventDefault();
            this.open().then(() => this.focusCalendar());
        } else if (event.key === 'Escape' && this.popup) {
            event.preventDefault();
            this.close();
        }
    }

    clear() {
        this.close(false);
        this.commit('', '');
        this.displayTarget.focus();
    }

    // Once vanilla-calendar-pro is there: right away from the second time on.
    async open() {
        if (this.popup || this.opening || !this.hasDisplayTarget) {
            return;
        }
        let library;
        this.opening = true;
        try {
            library = await loadCalendar();
        } catch (error) {
            console.error('@byfareska/crud: vanilla-calendar-pro could not be loaded.', error);

            return;
        } finally {
            this.opening = false;
        }
        // Gone or opened while it was loading.
        if (this.popup || !this.connected || !this.hasDisplayTarget) {
            return;
        }
        this.library = library;
        const popup = document.createElement('div');
        popup.className = 'crud-calendar';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-label', this.labelsValue.dialog ?? this.displayTarget.getAttribute('aria-label') ?? '');
        const host = document.createElement('div');
        popup.append(host);
        if (this.timeValue) {
            popup.append(this.timeFields());
        }
        (this.displayTarget.closest('dialog, .modal, .offcanvas') ?? document.body).append(popup);

        this.popup = popup;
        this.picking = null;
        this.calendar = new library.Calendar(host, this.calendarOptions());
        this.calendar.init();
        this.displayTarget.setAttribute('aria-expanded', 'true');
        this.place();

        this.onOutside = (event) => {
            if (!popup.contains(event.target) && event.target !== this.displayTarget) {
                this.close();
            }
        };
        this.onEscape = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.close();
                this.displayTarget.focus();
            }
        };
        this.onMove = () => this.place();
        document.addEventListener('pointerdown', this.onOutside, true);
        document.addEventListener('focusin', this.onOutside, true);
        popup.addEventListener('keydown', this.onEscape);
        window.addEventListener('resize', this.onMove);
        window.addEventListener('scroll', this.onMove, true);
    }

    // commit: a range left with its first day only is that one day.
    close(commit = true) {
        if (!this.popup) {
            return;
        }
        const picking = this.picking;
        document.removeEventListener('pointerdown', this.onOutside, true);
        document.removeEventListener('focusin', this.onOutside, true);
        window.removeEventListener('resize', this.onMove);
        window.removeEventListener('scroll', this.onMove, true);
        this.calendar.destroy();
        this.popup.remove();
        this.calendar = null;
        this.popup = null;
        this.picking = null;
        if (this.hasDisplayTarget) {
            this.displayTarget.setAttribute('aria-expanded', 'false');
        }
        if (commit && picking) {
            this.commitDays(picking, picking);
        }
    }

    reopen() {
        if (this.popup) {
            this.close(false);
            this.open();
        }
        this.refresh();
    }

    // The display follows the values.
    refresh() {
        if (!this.hasDisplayTarget) {
            return;
        }
        this.displayTarget.value = this.describe();
        this.displayTarget.title = this.displayTarget.value;
        this.displayTarget.placeholder = this.placeholderValue;
        if (this.hasClearTarget) {
            this.clearTarget.hidden = !this.from && !(this.rangeValue && this.to);
        }
    }

    calendarOptions() {
        const range = this.rangeValue;
        const start = this.day(this.from) || this.day(this.to);
        const startDate = start ? new Date(`${start}T00:00:00`) : new Date();
        const options = {
            locale: this.localeValue || document.documentElement.lang || 'en',
            firstWeekday: this.firstWeekday(),
            selectedWeekends: [],
            selectedMonth: startDate.getMonth(),
            selectedYear: startDate.getFullYear(),
            selectionDatesMode: range ? 'multiple-ranged' : 'single',
            enableDateToggle: !range,
            selectedDates: this.selectedDates(),
            onClickDate: (self) => this.picked(self.context.selectedDates),
        };
        if (range) {
            // Two months next to each other; one where there is no room for two.
            Object.assign(options, {
                type: 'multiple',
                extensions: [this.library.months],
                displayMonthsCount: window.matchMedia(`(min-width: ${this.breakpointValue}px)`).matches ? 2 : 1,
                monthsToSwitch: 1,
            });
        }

        return options;
    }

    // 0 Sunday … 6 Saturday: the value, else the locale's (weekInfo: 1 Monday … 7 Sunday), else Monday.
    firstWeekday() {
        if (this.hasFirstWeekdayValue) {
            return ((this.firstWeekdayValue % 7) + 7) % 7;
        }
        try {
            const tag = this.localeValue || document.documentElement.lang;
            const locale = tag ? new Intl.Locale(tag) : null;
            let info = null;
            if (locale) {
                info = typeof locale.getWeekInfo === 'function' ? locale.getWeekInfo() : locale.weekInfo;
            }
            if (info?.firstDay) {
                return info.firstDay % 7;
            }
        } catch {
            // A locale the browser does not know: Monday.
        }

        return 1;
    }

    selectedDates() {
        const from = this.day(this.from);
        if (!this.rangeValue) {
            return from ? [from] : [];
        }
        const to = this.day(this.to);
        if (from && to) {
            return from <= to ? [`${from}:${to}`] : [];
        }
        const one = from || to;

        return one ? [one] : [];
    }

    picked(dates) {
        if (!this.rangeValue) {
            this.picking = null;
            this.commitDays(dates[0] ?? '', '');
            if (!this.timeValue) {
                this.close(false);
                this.displayTarget.focus();
            }

            return;
        }
        if (dates.length < 2) {
            // The first day of a range: the second one closes it.
            this.picking = dates[0] ?? null;

            return;
        }
        this.picking = null;
        this.commitDays(dates[0], dates[dates.length - 1]);
        if (!this.timeValue) {
            this.close(false);
            this.displayTarget.focus();
        }
    }

    // The days chosen, with the times of the time fields (the whole day without them).
    commitDays(fromDay, toDay) {
        if (!this.timeValue) {
            this.commit(fromDay, toDay);

            return;
        }
        const fromTime = this.popup?.querySelector('[data-time="from"]')?.value || this.time(this.from) || '00:00';
        const toTime = this.popup?.querySelector('[data-time="to"]')?.value || this.time(this.to) || '23:59';
        this.commit(fromDay ? `${fromDay}T${fromTime}` : '', toDay ? `${toDay}T${toTime}` : '');
    }

    timeFields() {
        const box = document.createElement('div');
        box.className = 'crud-calendar__times';
        const parts = this.rangeValue
            ? [['from', this.labelsValue.from ?? 'From', this.time(this.from) || '00:00'],
                ['to', this.labelsValue.to ?? 'To', this.time(this.to) || '23:59']]
            : [['from', this.labelsValue.time ?? 'Time', this.time(this.from) || '00:00']];
        for (const [part, text, value] of parts) {
            const label = document.createElement('label');
            const caption = document.createElement('span');
            caption.textContent = text;
            const input = document.createElement('input');
            input.type = 'time';
            input.value = value;
            input.dataset.time = part;
            input.addEventListener('change', () => this.timeChanged());
            label.append(caption, input);
            box.append(label);
        }

        return box;
    }

    timeChanged() {
        const from = this.day(this.from);
        if (from) {
            this.commitDays(from, this.rangeValue ? this.day(this.to) || from : '');
        }
    }

    commit(from, to) {
        this.write(this.hasFromTarget ? this.fromTarget : null, from);
        this.write(this.rangeValue && this.hasToTarget ? this.toTarget : null, to);
        this.from = from;
        if (this.rangeValue) {
            this.to = to;
        }
        this.refresh();
    }

    write(input, value) {
        if (!input || input.value === value) {
            return;
        }
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Below the field, else above it where there is no room below; within the window.
    place() {
        const field = this.displayTarget.getBoundingClientRect();
        const box = this.popup.getBoundingClientRect();
        const below = field.bottom + 4;
        const top = below + box.height > window.innerHeight && field.top - box.height - 4 > 0
            ? field.top - box.height - 4
            : below;
        const left = Math.max(4, Math.min(field.left, window.innerWidth - box.width - 4));
        Object.assign(this.popup.style, { top: `${top}px`, left: `${left}px` });
    }

    focusCalendar() {
        const day = this.popup?.querySelector('[data-vc-date-selected] button, [data-vc-date-today] button, [data-vc-date] button');
        day?.focus();
    }

    describe() {
        const from = this.format(this.from);
        if (!this.rangeValue) {
            return from;
        }
        // Whole days (from midnight to 23:59) without the times.
        const days = this.time(this.from) === '00:00' && this.time(this.to) === '23:59';
        if (days) {
            return `${this.format(this.day(this.from))} — ${this.format(this.day(this.to))}`;
        }
        const to = this.format(this.to);
        if (!from && !to) {
            return '';
        }

        return `${from} — ${to}`.trim();
    }

    format(value) {
        const day = this.day(value);
        if (!day) {
            return '';
        }
        const time = this.time(value);
        const moment = new Date(`${day}T${time || '00:00'}`);
        if (Number.isNaN(moment.getTime())) {
            return value;
        }
        const options = { weekday: 'short', day: 'numeric', month: 'short' };
        if (moment.getFullYear() !== new Date().getFullYear()) {
            options.year = 'numeric';
        }
        if (time) {
            Object.assign(options, { hour: '2-digit', minute: '2-digit' });
        }

        return new Intl.DateTimeFormat(this.localeValue || undefined, options).format(moment);
    }

    // "2026-11-11" of "2026-11-11" and "2026-11-11T14:30".
    day(value) {
        return (value || '').slice(0, 10);
    }

    // "14:30" of "2026-11-11T14:30", "" of a date.
    time(value) {
        return (value || '').slice(11, 16);
    }
}
