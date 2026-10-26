<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { router, useForm } from '@inertiajs/vue3'
import { formatDate } from '@/date'

/**
 * Disponibilités. Ce qui est ouvert ici est la SEULE source du calendrier
 * client : rien n'est proposé au public qui ne soit déclaré ouvert.
 */
export default {
    layout: AdminLayout,
    components: { Icon },

    props: {
        weekly:     { type: Array, default: () => [] },
        breaks:     { type: Array, default: () => [] },
        exceptions:  { type: Array,  default: () => [] },
        slotStep:    { type: Number, default: 15 },
        stepOptions:    { type: Array,  default: () => [15, 30, 45, 60] },
        horizonDays:    { type: Number, default: 30 },
        horizonOptions: { type: Array,  default: () => [7, 14, 30] },
    },

    data() {
        return {
            // Ordre d'affichage : semaine française, dimanche en dernier
            days: [
                { value: 1, label: 'Lundi' },
                { value: 2, label: 'Mardi' },
                { value: 3, label: 'Mercredi' },
                { value: 4, label: 'Jeudi' },
                { value: 5, label: 'Vendredi' },
                { value: 6, label: 'Samedi' },
                { value: 0, label: 'Dimanche' },
            ],
            slots: this.weekly.map((s) => ({
                weekday: s.weekday,
                start_time: String(s.start_time).slice(0, 5),
                end_time: String(s.end_time).slice(0, 5),
                is_open: s.is_open,
            })),
            pauses: this.breaks.map((b) => ({
                weekday: b.weekday,
                start_time: String(b.start_time).slice(0, 5),
                end_time: String(b.end_time).slice(0, 5),
                label: b.label,
            })),
            step: this.slotStep,
            horizon: this.horizonDays,
            exceptionForm: useForm({
                type: 'closed', start_date: '', end_date: '',
                start_time: '', end_time: '', label: '',
            }),
        }
    },

    methods: {
        formatDate,

        addSlot() {
            this.slots.push({ weekday: 1, start_time: '10:00', end_time: '19:00', is_open: true })
        },
        addPause() {
            this.pauses.push({ weekday: 1, start_time: '13:00', end_time: '14:00', label: 'Pause déjeuner' })
        },

        horizonLabel(jours) {
            return {
                7:  'La semaine qui vient',
                14: 'Les deux prochaines semaines',
                30: 'Le mois qui vient',
            }[jours] || `${jours} jours`
        },

        /** Date butoir correspondante, pour rendre le choix concret. */
        horizonExample(jours) {
            const d = new Date()

            d.setDate(d.getDate() + jours)

            return 'Jusqu\'au ' + d.toLocaleDateString('fr-FR', {
                weekday: 'long', day: 'numeric', month: 'long',
            })
        },

        saveHorizon() {
            router.post(route('admin.availability.horizon'), { days: this.horizon }, { preserveScroll: true })
        },

        /** Libellé parlant : « 30 min » dit moins que « à l'heure et à la demie ». */
        stepLabel(pas) {
            return {
                15: 'Tous les quarts d\'heure',
                30: 'À l\'heure et à la demie',
                45: 'Toutes les 45 minutes',
                60: 'À l\'heure pile uniquement',
            }[pas] || `Toutes les ${pas} minutes`
        },

        stepExample(pas) {
            const heures = []

            for (let m = 600; m < 720; m += pas) {
                heures.push(m % 60 ? `${Math.floor(m / 60)}h${String(m % 60).padStart(2, '0')}` : `${Math.floor(m / 60)}h`)
            }

            return heures.join(' · ') + ' …'
        },

        saveStep() {
            router.post(route('admin.availability.step'), { step: this.step }, { preserveScroll: true })
        },

        saveWeekly() {
            router.post(route('admin.availability.weekly'), { slots: this.slots }, { preserveScroll: true })
        },
        savePauses() {
            router.post(route('admin.availability.breaks'), { breaks: this.pauses }, { preserveScroll: true })
        },

        addException() {
            this.exceptionForm.post(route('admin.availability.exceptions.store'), {
                preserveScroll: true,
                onSuccess: () => this.exceptionForm.reset(),
            })
        },
        removeException(id) {
            if (confirm('Supprimer cette exception ?')) {
                router.delete(route('admin.availability.exceptions.destroy', id), { preserveScroll: true })
            }
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Disponibilités</h1>
    <p class="admin-sub">
        Seuls les créneaux ouverts ici apparaissent aux clients. Les pauses et les
        exceptions viennent s'en retrancher.
    </p>

    <section class="card admin-card">
        <h2 class="admin-card-title">Ouverture du calendrier</h2>
        <p class="field-help" style="margin-top: -6px">
            Jusqu'où les clients peuvent réserver à l'avance. Au-delà, le calendrier
            reste visible mais aucun créneau n'y est proposé.
        </p>

        <div class="pick-list">
            <label class="check" v-for="jours in horizonOptions" :key="jours">
                <input type="radio" :value="jours" v-model.number="horizon">
                <span>
                    <strong>{{ horizonLabel(jours) }}</strong>
                    <span class="pick-example">{{ horizonExample(jours) }}</span>
                </span>
            </label>
        </div>

        <div class="row-actions" style="margin-top: 16px">
            <button type="button" class="btn btn--solid btn--sm"
                    :disabled="horizon === horizonDays" @click="saveHorizon">Enregistrer</button>
        </div>
    </section>

    <section class="card admin-card">
        <h2 class="admin-card-title">Pas des créneaux</h2>
        <p class="field-help" style="margin-top: -6px">
            Heures de départ proposées aux clients. La grille est calée sur l'heure
            pleine : avec un pas d'une heure, un salon qui ouvre à 9h30 propose 10h,
            pas 9h30. La durée des prestations, elle, ne change pas.
        </p>

        <div class="pick-list">
            <label class="check" v-for="pas in stepOptions" :key="pas">
                <input type="radio" :value="pas" v-model.number="step">
                <span>
                    <strong>{{ stepLabel(pas) }}</strong>
                    <span class="pick-example">{{ stepExample(pas) }}</span>
                </span>
            </label>
        </div>

        <div class="row-actions" style="margin-top: 16px">
            <button type="button" class="btn btn--solid btn--sm"
                    :disabled="step === slotStep" @click="saveStep">Enregistrer</button>
        </div>
    </section>

    <section class="card admin-card">
        <h2 class="admin-card-title">Horaires de la semaine</h2>

        <div class="row-list">
            <div class="row-item hours-row" v-for="(slot, i) in slots" :key="i">
                <select v-model.number="slot.weekday">
                    <option v-for="d in days" :key="d.value" :value="d.value">{{ d.label }}</option>
                </select>
                <input type="time" v-model="slot.start_time" aria-label="Heure d'ouverture">
                <input type="time" v-model="slot.end_time" aria-label="Heure de fermeture">
                <!-- Pas de classe « field » ici : sa marge basse décalerait la
                     case vers le haut dans une ligne alignée au centre. -->
                <label class="check">
                    <input type="checkbox" v-model="slot.is_open"> Ouvert
                </label>
                <button type="button" class="btn-icon btn-icon--danger" aria-label="Retirer"
                        @click="slots.splice(i, 1)">
                    <Icon name="trash" :size="16" />
                </button>
            </div>
        </div>

        <div class="row-actions" style="margin-top: 16px">
            <button type="button" class="btn btn--ghost btn--sm" @click="addSlot">
                <Icon name="plus" :size="16" /> Ajouter une plage
            </button>
            <button type="button" class="btn btn--solid btn--sm" @click="saveWeekly">Enregistrer</button>
        </div>
    </section>

    <section class="card admin-card">
        <h2 class="admin-card-title">Pauses hebdomadaires</h2>

        <div class="row-list">
            <div class="row-item hours-row" v-for="(pause, i) in pauses" :key="i">
                <select v-model.number="pause.weekday">
                    <option v-for="d in days" :key="d.value" :value="d.value">{{ d.label }}</option>
                </select>
                <input type="time" v-model="pause.start_time" aria-label="Début de la pause">
                <input type="time" v-model="pause.end_time" aria-label="Fin de la pause">
                <input type="text" v-model="pause.label" placeholder="Intitulé" class="grow">
                <button type="button" class="btn-icon btn-icon--danger" aria-label="Retirer"
                        @click="pauses.splice(i, 1)">
                    <Icon name="trash" :size="16" />
                </button>
            </div>
        </div>

        <div class="row-actions" style="margin-top: 16px">
            <button type="button" class="btn btn--ghost btn--sm" @click="addPause">
                <Icon name="plus" :size="16" /> Ajouter une pause
            </button>
            <button type="button" class="btn btn--solid btn--sm" @click="savePauses">Enregistrer</button>
        </div>
    </section>

    <section class="card admin-card">
        <h2 class="admin-card-title">Congés et ouvertures exceptionnelles</h2>
        <p class="field-help">
            Une fermeture prime toujours sur l'horaire habituel. Une ouverture
            exceptionnelle le remplace pour les dates concernées.
        </p>

        <div class="field-row">
            <label class="field">
                <span class="field-label">Type</span>
                <select v-model="exceptionForm.type">
                    <option value="closed">Fermeture</option>
                    <option value="open">Ouverture exceptionnelle</option>
                </select>
            </label>
            <label class="field">
                <span class="field-label">Intitulé</span>
                <input type="text" v-model="exceptionForm.label" placeholder="Congés, jour férié…">
            </label>
            <label class="field">
                <span class="field-label">Du</span>
                <input type="date" v-model="exceptionForm.start_date">
            </label>
            <label class="field">
                <span class="field-label">Au</span>
                <input type="date" v-model="exceptionForm.end_date">
            </label>
            <template v-if="exceptionForm.type === 'open'">
                <label class="field">
                    <span class="field-label">Début</span>
                    <input type="time" v-model="exceptionForm.start_time">
                </label>
                <label class="field">
                    <span class="field-label">Fin</span>
                    <input type="time" v-model="exceptionForm.end_time">
                </label>
            </template>
        </div>

        <button type="button" class="btn btn--solid btn--sm" @click="addException">Ajouter</button>

        <div class="row-list" style="margin-top: 20px" v-if="exceptions.length">
            <div class="row-item" v-for="ex in exceptions" :key="ex.id">
                <div class="row-main">
                    <p class="row-title">{{ ex.label || (ex.type === 'closed' ? 'Fermeture' : 'Ouverture') }}</p>
                    <p class="row-meta">
                        {{ formatDate(ex.start_date) }} → {{ formatDate(ex.end_date) }}
                        <span v-if="ex.start_time"> · {{ String(ex.start_time).slice(0, 5) }}–{{ String(ex.end_time).slice(0, 5) }}</span>
                    </p>
                </div>
                <span class="badge" :class="ex.type === 'closed' ? 'badge--danger' : 'badge--green'">
                    {{ ex.type === 'closed' ? 'Fermé' : 'Ouvert' }}
                </span>
                <button type="button" class="btn-icon btn-icon--danger" aria-label="Supprimer"
                        @click="removeException(ex.id)">
                    <Icon name="trash" :size="16" />
                </button>
            </div>
        </div>
    </section>
</template>
