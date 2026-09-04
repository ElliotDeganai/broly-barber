<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { router, useForm } from '@inertiajs/vue3'
import { formatDateTime, formatDateLong, formatMonth, money } from '@/date'
import { statusLabel, statusClass, STATUSES } from '@/status'

/**
 * Rendez-vous du mois : validation, refus, contre-proposition de 1 à 3
 * créneaux, passage en réalisé.
 */
export default {
    layout: AdminLayout,
    components: { Icon },

    props: {
        month:        { type: String, required: true },
        status:       { type: String, default: null },
        search:       { type: String, default: null },
        day:          { type: String, default: null },
        focus:        { type: Object, default: null },
        appointments: { type: Array,  default: () => [] },
        calendar:     { type: Array,  default: () => [] },
    },

    data() {
        return {
            statuses: STATUSES,
            // Le jour vient du serveur : il survit au rechargement de la page
            q: this.search || '',
            counterFor: null,
            counterNote: '',
            // Créneaux libres reçus du serveur, groupés par jour
            counterSlots: {},
            counterDay: null,
            counterPicked: [],
            counterLoading: false,
            refuseFor: null,
            refuseForm: useForm({ reason: '' }),
        }
    },

    computed: {
        monthLabel() {
            return formatMonth(this.month)
        },

        weekdays() {
            return ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
        },

        /**
         * Cases vides avant le 1er du mois.
         *
         * Sans ce décalage, le 1er se placerait toujours sous « Lun » quel que
         * soit le jour réel, et toute la grille serait fausse.
         */
        leadingBlanks() {
            const first = this.calendar[0]
            if (!first) return 0

            const [y, m, d] = first.date.split('-').map(Number)

            // Construction explicite : new Date('2026-09-01') serait interprété
            // en UTC et pourrait basculer d'un jour selon le fuseau.
            const weekday = new Date(y, m - 1, d).getDay()

            // getDay() compte à partir de dimanche ; la semaine française
            // commence lundi.
            return (weekday + 6) % 7
        },

        selectedDayLabel() {
            return this.day ? formatDateLong(this.day) : null
        },

        counterDays() {
            return Object.keys(this.counterSlots)
        },

        /** Y a-t-il au moins un filtre actif ? Conditionne le bandeau de reset. */
        hasFilters() {
            return Boolean(this.status || this.search || this.day)
        },
    },

    mounted() {
        if (!this.focus) return

        // Le rendez-vous ciblé est amené à l'écran et signalé. nextTick :
        // la liste n'est pas encore rendue au moment du montage.
        this.$nextTick(() => {
            const el = this.$refs['appointment' + this.focus.id]?.[0]
            if (!el) return

            el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        })
    },

    methods: {
        formatDateTime,
        formatDateLong,
        money,
        statusLabel,
        statusClass,

        /**
         * Un seul point d'entrée pour tous les filtres.
         *
         * Chacun ne fournit que ce qu'il change ; le reste est conservé. Sans
         * cela, chercher un client effacerait le filtre de statut, et changer
         * de mois effacerait la recherche.
         */
        applyFilters(changes = {}) {
            const params = {
                month:  this.month,
                status: this.status || undefined,
                q:      this.q || undefined,
                day:    this.day || undefined,
                ...changes,
            }

            // Les valeurs vides sont retirées pour garder une URL lisible
            Object.keys(params).forEach((k) => {
                if (!params[k]) delete params[k]
            })

            router.get(route('admin.appointments.index'), params, {
                preserveScroll: true,
                preserveState: true,
            })
        },

        shiftMonth(delta) {
            const d = new Date(this.month)
            d.setMonth(d.getMonth() + delta)

            // Changer de mois annule le jour : il n'existe plus dans la grille
            this.applyFilters({ month: d.toISOString().slice(0, 10), day: undefined })
        },

        resetFilters() {
            this.q = ''
            this.applyFilters({ status: undefined, q: undefined, day: undefined })
        },

        confirm(id) {
            router.post(route('admin.appointments.confirm', id), {}, { preserveScroll: true })
        },

        complete(id) {
            router.post(route('admin.appointments.complete', id), {}, { preserveScroll: true })
        },

        openRefuse(id) {
            this.refuseFor = id
            this.refuseForm.reason = ''
        },

        submitRefuse() {
            this.refuseForm.post(route('admin.appointments.refuse', this.refuseFor), {
                preserveScroll: true,
                onSuccess: () => { this.refuseFor = null },
            })
        },

        async openCounter(id) {
            this.counterFor = id
            this.counterNote = ''
            this.counterPicked = []
            this.counterSlots = {}
            this.counterDay = null
            this.counterLoading = true

            try {
                const response = await fetch(route('admin.appointments.slots', id), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })

                if (!response.ok) throw new Error(response.status)

                const data = await response.json()

                this.counterSlots = data.slots || {}
                this.counterDay = Object.keys(this.counterSlots)[0] || null
            } catch {
                // Échec du chargement : on referme plutôt que d'afficher un
                // sélecteur vide, qui laisserait croire à l'absence de créneaux.
                this.counterFor = null
                alert('Impossible de charger les créneaux disponibles.')
            } finally {
                this.counterLoading = false
            }
        },

        /** Trois créneaux au maximum, un second clic retire la sélection. */
        togglePick(day, time) {
            const value = `${day} ${time}:00`
            const i = this.counterPicked.indexOf(value)

            if (i !== -1) {
                this.counterPicked.splice(i, 1)
            } else if (this.counterPicked.length < 3) {
                this.counterPicked.push(value)
            }
        },

        isPicked(day, time) {
            return this.counterPicked.includes(`${day} ${time}:00`)
        },

        submitCounter() {
            if (!this.counterPicked.length) return

            router.post(route('admin.appointments.counter', this.counterFor), {
                slots: this.counterPicked,
                note: this.counterNote,
            }, {
                preserveScroll: true,
                onSuccess: () => { this.counterFor = null },
            })
        },

        dayNumber(date) {
            return Number(date.split('-')[2])
        },

        /** Un second clic sur le même jour revient au mois entier. */
        selectDay(dayCell) {
            this.applyFilters({ day: this.day === dayCell.date ? undefined : dayCell.date })
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Rendez-vous</h1>
    <p class="admin-sub">{{ monthLabel }} — {{ appointments.length }} rendez-vous.</p>

    <section class="card admin-card">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px">
            <button type="button" class="btn-icon" aria-label="Mois précédent" @click="shiftMonth(-1)">
                <Icon name="left" :size="20" />
            </button>
            <span class="admin-card-title" style="margin: 0">{{ monthLabel }}</span>
            <button type="button" class="btn-icon" aria-label="Mois suivant" @click="shiftMonth(1)">
                <Icon name="right" :size="20" />
            </button>
        </div>

        <!-- Charge de chaque journée : repère visuel avant d'ouvrir la liste.
             Un clic filtre la liste sur ce jour. -->
        <div class="month-grid">
            <span v-for="label in weekdays" :key="label" class="month-head">{{ label }}</span>

            <!-- Cases vides pour aligner le 1er sur le bon jour de la semaine -->
            <span v-for="n in leadingBlanks" :key="`blank${n}`" class="month-blank"></span>

            <button
                v-for="cell in calendar"
                :key="cell.date"
                type="button"
                class="month-cell"
                :class="{ 'is-closed': cell.open_min === 0, 'is-selected': day === cell.date }"
                :title="`${cell.used_min} min réservées sur ${cell.open_min} ouvertes`"
                :aria-pressed="day === cell.date"
                @click="selectDay(cell)"
            >
                <span>{{ dayNumber(cell.date) }}</span>
                <span class="month-bar" v-if="cell.open_min">
                    <span :style="{ width: Math.min(cell.load, 100) + '%' }"></span>
                </span>
            </button>
        </div>
    </section>

    <section class="card admin-card">
        <!-- Filtres. Chacun conserve les autres : chercher un client ne doit
             pas effacer le filtre de statut. -->
        <div class="filter-bar">
            <label class="field">
                <span class="field-label">Client</span>
                <input
                    type="text"
                    v-model="q"
                    placeholder="Nom, email ou téléphone"
                    @keyup.enter="applyFilters({ q: q || undefined })"
                    @search="applyFilters({ q: q || undefined })"
                >
            </label>

            <label class="field">
                <span class="field-label">Statut</span>
                <select :value="status || ''" @change="applyFilters({ status: $event.target.value || undefined })">
                    <option value="">Tous</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Jour</span>
                <input type="date" :value="day || ''"
                       @change="applyFilters({ day: $event.target.value || undefined })">
            </label>

            <button type="button" class="btn btn--solid btn--sm"
                    @click="applyFilters({ q: q || undefined })">Filtrer</button>
        </div>

        <p class="day-filter" v-if="hasFilters">
            <span>
                {{ appointments.length }} rendez-vous
                <template v-if="day"> · {{ selectedDayLabel }}</template>
                <template v-if="search"> · « {{ search }} »</template>
                <template v-if="focus"> · demande signalée ci-dessous</template>
            </span>
            <button type="button" class="btn btn--ghost btn--sm" @click="resetFilters">
                Retirer les filtres
            </button>
        </p>

        <div class="row-list" v-if="appointments.length">
            <div
                class="row-item"
                v-for="a in appointments"
                :key="a.id"
                :ref="'appointment' + a.id"
                :class="{ 'is-focused': focus && focus.id === a.id }"
            >
                <div class="row-main">
                    <p class="row-title">
                        {{ formatDateTime(a.starts_at) }} — {{ a.client }}
                        <span v-if="a.is_late_night" class="badge badge--yellow" style="margin-left: 8px">Tarif ×2</span>
                    </p>
                    <p class="row-meta">
                        {{ a.service }} · {{ a.duration_min }} min · {{ money(a.total_due) }}
                        <span v-if="a.debt > 0" style="color: var(--danger)"> · dette {{ money(a.debt) }}</span>
                    </p>
                    <p class="row-meta" v-if="a.comment">« {{ a.comment }} »</p>
                    <p class="row-meta" v-if="a.proposals.length">
                        Créneaux proposés :
                        <span v-for="p in a.proposals" :key="p.id">{{ formatDateTime(p.starts_at) }} </span>
                    </p>
                </div>

                <span class="badge" :class="statusClass(a.status)">{{ statusLabel(a.status) }}</span>

                <div class="row-actions">
                    <button v-if="a.status === 'pending'" type="button" class="btn btn--solid btn--sm"
                            @click="confirm(a.id)">Confirmer</button>
                    <button v-if="a.status === 'pending'" type="button" class="btn btn--ghost btn--sm"
                            @click="openCounter(a.id)">Autre créneau</button>
                    <button v-if="a.status === 'pending'" type="button" class="btn btn--ghost btn--sm"
                            @click="openRefuse(a.id)">Refuser</button>
                    <button v-if="['confirmed', 'counter_accepted'].includes(a.status)"
                            type="button" class="btn btn--solid btn--sm" @click="complete(a.id)">Réalisé</button>
                </div>

                <!-- Contre-proposition : de 1 à 3 créneaux, choisis parmi les
                     disponibilités réelles pour cette prestation. -->
                <div v-if="counterFor === a.id" class="counter-panel">
                    <p class="field-help" v-if="counterLoading">Chargement des créneaux…</p>

                    <template v-else-if="counterDays.length">
                        <p class="field-help">
                            Sélectionnez jusqu'à trois créneaux ({{ counterPicked.length }}/3).
                            Seules les disponibilités réelles pour cette prestation sont proposées.
                        </p>

                        <div class="counter-days">
                            <button
                                v-for="d in counterDays"
                                :key="d"
                                type="button"
                                class="badge"
                                :class="counterDay === d ? 'badge--yellow' : 'badge--green'"
                                style="border: none"
                                @click="counterDay = d"
                            >{{ formatDateLong(d) }}</button>
                        </div>

                        <div class="counter-slots" v-if="counterDay">
                            <button
                                v-for="t in counterSlots[counterDay]"
                                :key="t"
                                type="button"
                                class="btn btn--ghost btn--sm"
                                :class="{ 'is-picked': isPicked(counterDay, t) }"
                                @click="togglePick(counterDay, t)"
                            >{{ t }}</button>
                        </div>

                        <ul class="counter-picked" v-if="counterPicked.length">
                            <li v-for="p in counterPicked" :key="p">{{ formatDateTime(p.replace(' ', 'T')) }}</li>
                        </ul>

                        <label class="field" style="margin-top: 14px">
                            <span class="field-label">Message</span>
                            <input type="text" v-model="counterNote" placeholder="Optionnel">
                        </label>

                        <div class="row-actions">
                            <button type="button" class="btn btn--solid btn--sm"
                                    :disabled="!counterPicked.length" @click="submitCounter">
                                Envoyer {{ counterPicked.length ? `(${counterPicked.length})` : '' }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm" @click="counterFor = null">Annuler</button>
                        </div>
                    </template>

                    <template v-else>
                        <p class="empty-state">
                            Aucun créneau libre pour cette prestation dans les prochaines semaines.
                            Ouvrez des disponibilités avant de proposer une alternative.
                        </p>
                        <button type="button" class="btn btn--ghost btn--sm" @click="counterFor = null">Fermer</button>
                    </template>
                </div>

                <div v-if="refuseFor === a.id" style="flex: 1 1 100%; border-top: 1px solid var(--border); padding-top: 14px">
                    <label class="field">
                        <span class="field-label">Motif du refus</span>
                        <input type="text" v-model="refuseForm.reason" placeholder="Communiqué au client">
                    </label>
                    <div class="row-actions">
                        <button type="button" class="btn btn--solid btn--sm" @click="submitRefuse">Refuser</button>
                        <button type="button" class="btn btn--ghost btn--sm" @click="refuseFor = null">Annuler</button>
                    </div>
                </div>
            </div>
        </div>

        <p v-else class="empty-state">
            {{ hasFilters ? 'Aucun rendez-vous ne correspond à ces filtres.' : 'Aucun rendez-vous ce mois-ci.' }}
        </p>
    </section>
</template>
