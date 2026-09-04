<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import { router } from '@inertiajs/vue3'
import { formatDateLong, money, toServerDateTime } from '@/date'

/**
 * Étape 2 : créneaux réellement ouverts.
 *
 * Tous les jours arrivent en une fois : recharger à chaque changement de date
 * rendrait la navigation pénible sur mobile.
 */
export default {
    layout: AppLayout,
    components: { Icon },

    props: {
        service: { type: Object, required: true },
        slots:   { type: Object, default: () => ({}) },
        from:    { type: String, required: true },
        quote:   { type: Object, default: () => ({}) },
    },

    data() {
        const days = Object.keys(this.slots)

        return {
            days,
            selectedDay: days[0] || null,
        }
    },

    computed: {
        daySlots() {
            return this.selectedDay ? this.slots[this.selectedDay] || [] : []
        },
        hasSlots() {
            return this.days.length > 0
        },
    },

    methods: {
        formatDateLong,
        money,

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

                <div class="carousel" style="gap: 8px">
                    <button
                        v-for="day in days"
                        :key="day"
                        type="button"
                        class="badge"
                        :class="selectedDay === day ? 'badge--yellow' : 'badge--green'"
                        style="flex: 0 0 auto; border: none; padding: 10px 14px"
                        @click="selectedDay = day"
                    >{{ formatDateLong(day) }}</button>
                </div>
            </div>

            <div class="card admin-card">
                <h2 class="admin-card-title">Créneaux disponibles</h2>

                <div class="thumb-grid" style="grid-template-columns: repeat(auto-fill, minmax(92px, 1fr))">
                    <button
                        v-for="time in daySlots"
                        :key="time"
                        type="button"
                        class="btn btn--ghost"
                        style="padding: 12px 0"
                        @click="pick(time)"
                    >{{ time }}</button>
                </div>

                <p v-if="!daySlots.length" class="empty-state">
                    Aucun créneau libre ce jour-là.
                </p>
            </div>
        </template>

        <p v-else class="empty-state card" style="padding: 34px 18px">
            Aucun créneau n'est ouvert pour le moment. Revenez plus tard ou
            contactez le studio.
        </p>
    </div>
</template>
