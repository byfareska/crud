import { Controller } from '@hotwired/stimulus';

/*
 * A clock of the dashboard (Panel:ClockWidget), ticking in the browser - the server is not asked. Each zone of the
 * widget is a `face` target: an analog face (hands as targets) or digital text, in its time zone.
 *
 * <div data-controller="clock" data-clock-locale-value="pl">
 *     <div data-clock-target="face" data-zone="Europe/Warsaw"> … data-hand="hour|minute|second" … data-digital … </div>
 */
export default class extends Controller {
    static targets = ['face'];
    static values = { locale: String };

    connect() {
        this.formats = new Map();
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    tick() {
        const now = new Date();
        for (const face of this.faceTargets) {
            const zone = face.dataset.zone || undefined;
            const [h, m, s] = this.parts(now, zone);
            this.turn(face, 'hour', (h % 12) * 30 + m * 0.5);
            this.turn(face, 'minute', m * 6 + s * 0.1);
            this.turn(face, 'second', s * 6);
            const digital = face.querySelector('[data-digital]');
            if (digital) {
                digital.textContent = this.format(zone, { hour: '2-digit', minute: '2-digit' }).format(now);
            }
            const day = face.querySelector('[data-day]');
            if (day) {
                day.textContent = this.format(zone, { weekday: 'long', day: 'numeric', month: 'long' }).format(now);
            }
        }
    }

    parts(date, zone) {
        const parts = this.format(zone, { hour: 'numeric', minute: 'numeric', second: 'numeric', hourCycle: 'h23' }).formatToParts(date);
        const value = (type) => Number(parts.find((part) => part.type === type)?.value ?? 0);

        return [value('hour'), value('minute'), value('second')];
    }

    format(zone, options) {
        const key = `${zone}|${JSON.stringify(options)}`;
        if (!this.formats.has(key)) {
            this.formats.set(key, new Intl.DateTimeFormat(this.localeValue || undefined, { ...options, timeZone: zone }));
        }

        return this.formats.get(key);
    }

    turn(face, name, degrees) {
        face.querySelector(`[data-hand="${name}"]`)?.setAttribute('transform', `rotate(${degrees} 50 50)`);
    }
}
