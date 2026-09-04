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
        exceptions: { type: Array, default: () => [] },
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
        <h2 class="admin-card-title">Horaires de la semaine</h2>

        <div class="row-list">
            <div class="row-item" v-for="(slot, i) in slots" :key="i">
                <select v-model.number="slot.weekday" style="flex: 0 0 140px">
                    <option v-for="d in days" :key="d.value" :value="d.value">{{ d.label }}</option>
                </select>
                <input type="time" v-model="slot.start_time" style="flex: 0 0 120px">
                <input type="time" v-model="slot.end_time" style="flex: 0 0 120px">
                <label class="check">
                    <input type="checkbox" v-model="slot.is_open">
                    <span>Ouvert</span>
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
            <div class="row-item" v-for="(pause, i) in pauses" :key="i">
                <select v-model.number="pause.weekday" style="flex: 0 0 140px">
                    <option v-for="d in days" :key="d.value" :value="d.value">{{ d.label }}</option>
                </select>
                <input type="time" v-model="pause.start_time" style="flex: 0 0 120px">
                <input type="time" v-model="pause.end_time" style="flex: 0 0 120px">
                <input type="text" v-model="pause.label" placeholder="Intitulé" style="flex: 1 1 140px">
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
