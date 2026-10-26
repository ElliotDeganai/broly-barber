<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import { router } from '@inertiajs/vue3'
import { formatDateLong, formatMonth, money, toServerDateTime, todayInStudioTz } from '@/date'

/**
 * Étape 2 : créneaux réellement ouverts.
 *
 * Tous les jours arrivent en une fois : recharger à chaque changement de date
 * rendrait la navigation pénible sur mobile. Le calendrier, lui, se navigue
 * librement — un mois au-delà de l'horizon s'affiche simplement vide, ce que
 * la mention sous la grille explique.
 */
export default {
    layout: AppLayout,
    components: { Icon },

    props: {
        service: { type: Object, required: true },
        slots:   { type: Object, default: () => ({}) },
        from:    { type: String, required: true },
        horizon:     { type: String, default: null },
        horizonDays: { type: Number, default: 30 },
        today:       { type: String, default: null },
        quote:   { type: Object, default: () => ({}) },
    },

    data() {
        const days = Object.keys(this.slots).sort()
        const premier = days[0] || this.from

        return {
            days,
            selectedDay: days[0] || null,
            // Ancre du calendrier : le mois du premier jour réservable, et non
            // le mois courant — ouvrir sur un mois sans aucun créneau serait
            // une impasse.
            month: premier.slice(0, 7) + '-01',
        }
    },

    computed: {
        hasSlots() {
            return this.days.length > 0
        },

        monthLabel() {
            return formatMonth(this.month)
        },

        weekdays() {
            return ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
        },

        /**
         * Cases vides avant le 1er du mois.
         *
         * Sans ce décalage le 1er se placerait toujours sous « Lun », et toute
         * la grille serait fausse.
         */
        leadingBlanks() {
            const [y, m] = this.month.split('-').map(Number)

            // Construction explicite : new Date('2026-11-01') serait lu en UTC
            // et pourrait basculer d'un jour selon le fuseau.
            return (new Date(y, m - 1, 1).getDay() + 6) % 7
        },

        /** Jours du mois affiché, avec le nombre de créneaux encore libres. */
        cells() {
            const [y, m] = this.month.split('-').map(Number)
            const dernier = new Date(y, m, 0).getDate()
            const out = []

            for (let n = 1; n <= dernier; n++) {
                const date = `${y}-${String(m).padStart(2, '0')}-${String(n).padStart(2, '0')}`
                const free = (this.slots[date] || []).length

                /*
                 * Trois raisons de ne pas pouvoir réserver, qui n'ont rien à
                 * voir entre elles. « Passé » et « complet » sont définitifs,
                 * « pas encore ouvert » ne l'est pas : le confondre avec les
                 * deux autres ferait renoncer un client qui pourra réserver
                 * dans quelques jours.
                 */
                let state = 'free'

                if (free === 0) {
                    if (this.todayDate && date < this.todayDate) state = 'past'
                    else if (this.horizon && date > this.horizon) state = 'later'
                    else state = 'full'
                }

                out.push({ date, number: n, free, state })
            }

            return out
        },

        todayDate() {
            return this.today || todayInStudioTz()
        },

        monthHasFree() {
            return this.cells.some((c) => c.free > 0)
        },

        /** Le mois affiché est-il entièrement au-delà de l'horizon ? */
        monthIsLater() {
            return !this.monthHasFree && this.cells.every((c) => c.state === 'later')
        },

        /** Le mois affiché contient-il des jours pas encore ouverts ? */
        monthHasLater() {
            return this.cells.some((c) => c.state === 'later')
        },

        /**
         * Date à laquelle le premier jour pas encore ouvert du mois le
         * deviendra : le calendrier avance d'un jour par jour.
         */
        openingDate() {
            const premier = this.cells.find((c) => c.state === 'later')

            if (!premier) return null

            const [y, m, d] = premier.date.split('-').map(Number)
            const ouverture = new Date(y, m - 1, d)

            ouverture.setDate(ouverture.getDate() - this.horizonDays)

            return formatDateLong(ouverture)
        },

        daySlots() {
            return this.selectedDay ? this.slots[this.selectedDay] || [] : []
        },

        selectedLabel() {
            return this.selectedDay ? formatDateLong(this.selectedDay) : null
        },

        horizonLabel() {
            return this.horizon ? formatDateLong(this.horizon) : null
        },
    },

    methods: {
        money,

        shiftMonth(delta) {
            const [y, m] = this.month.split('-').map(Number)
            const d = new Date(y, m - 1, 1)

            d.setMonth(d.getMonth() + delta)

            this.month = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
        },

        /** « 10:00 » stocké, « 10h00 » affiché. */
        timeLabel(time) {
            return time.replace(':', 'h')
        },

        pick(time) {
            router.get(route('booking.confirm', this.service.slug), {
                starts_at: toServerDateTime(this.selectedDay, time),
            })
        },
    },
}
</script>

<template>
    <div class="shell section">
        <p class="badge badge--green">Étape 2 sur 3</p>
        <h1 class="title-glow" style="margin: 14px 0 8px">{{ service.name }}</h1>
        <p class="lede" style="margin-bottom: 24px">
            {{ service.duration_min }} min · à partir de {{ money(service.price) }}
        </p>

        <template v-if="hasSlots">
            <div class="card admin-card">
                <h2 class="admin-card-title">Choisissez un jour</h2>

                <div class="book-cal">
                    <div class="month-nav">
                        <button type="button" class="btn-icon" aria-label="Mois précédent"
                                @click="shiftMonth(-1)">
                            <Icon name="left" :size="20" />
                        </button>
                        <span>{{ monthLabel }}</span>
                        <button type="button" class="btn-icon" aria-label="Mois suivant"
                                @click="shiftMonth(1)">
                            <Icon name="right" :size="20" />
                        </button>
                    </div>

                    <div class="month-grid">
                        <span v-for="label in weekdays" :key="label" class="month-head">{{ label }}</span>

                        <span v-for="n in leadingBlanks" :key="`blank${n}`" class="month-blank"></span>

                        <button
                            v-for="cell in cells"
                            :key="cell.date"
                            type="button"
                            class="month-cell"
                            :class="{
                                'is-closed': cell.state === 'past' || cell.state === 'full',
                                'is-later': cell.state === 'later',
                                'is-selected': selectedDay === cell.date,
                            }"
                            :disabled="!cell.free"
                            :aria-pressed="selectedDay === cell.date"
                            :title="cell.state === 'free' ? `${cell.free} créneau(x) libre(s)`
                                : (cell.state === 'later' ? 'Pas encore ouvert à la réservation'
                                : (cell.state === 'past' ? 'Date passée' : 'Aucun créneau'))"
                            @click="selectedDay = cell.date"
                        >
                            <span>{{ cell.number }}</span>
                            <span class="month-free" v-if="cell.free">{{ cell.free }}</span>
                        </button>
                    </div>
                </div>

                <!-- Un mois entièrement au-delà de l'horizon n'est pas un mois
                     complet : le dire évite qu'un client renonce. -->
                <p class="notice-soon" v-if="monthIsLater">
                    <Icon name="clock" :size="18" />
                    <span>
                        <strong>Les réservations de {{ monthLabel }} ne sont pas encore ouvertes.</strong>
                        Le calendrier s'ouvre {{ horizonDays }} jours à l'avance.
                        <template v-if="openingDate">
                            Les premiers créneaux de ce mois apparaîtront le {{ openingDate }}.
                        </template>
                    </span>
                </p>

                <p class="empty-state" v-else-if="!monthHasFree" style="padding: 18px 12px">
                    Aucun créneau en {{ monthLabel }}.
                </p>

                <p class="field-help" style="margin-top: 14px">
                    Le chiffre sous chaque jour indique le nombre de créneaux encore libres.
                    <template v-if="monthHasLater && !monthIsLater">
                        Les jours en pointillés ne sont pas encore ouverts à la réservation :
                        le calendrier s'ouvre {{ horizonDays }} jours à l'avance.
                    </template>
                    <template v-else-if="horizonLabel">
                        Les réservations sont ouvertes jusqu'au {{ horizonLabel }}.
                    </template>
                </p>
            </div>

            <div class="card admin-card">
                <div class="day-head">
                    <h2 class="admin-card-title" style="margin: 0">Créneaux disponibles</h2>
                    <strong v-if="selectedLabel">{{ selectedLabel }}</strong>
                </div>

                <div class="slot-grid" v-if="daySlots.length">
                    <button
                        v-for="time in daySlots"
                        :key="time"
                        type="button"
                        class="slot"
                        @click="pick(time)"
                    >{{ timeLabel(time) }}</button>
                </div>

                <p v-else class="empty-state">Aucun créneau libre ce jour-là.</p>
            </div>
        </template>

        <p v-else class="empty-state card" style="padding: 34px 18px">
            Aucun créneau n'est ouvert pour le moment. Revenez plus tard ou
            contactez le studio.
        </p>
    </div>
</template>
