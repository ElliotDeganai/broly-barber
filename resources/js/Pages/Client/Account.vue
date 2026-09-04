<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import { Link, router } from '@inertiajs/vue3'
import { formatDate, formatDateLong, formatTime, money } from '@/date'
import { statusLabel, statusClass } from '@/status'

/** Espace client : rendez-vous, fidélité, dette. */
export default {
    layout: AppLayout,
    components: { Icon, Link },

    props: {
        loyalty:      { type: Object, default: () => ({}) },
        debt:         { type: Object, default: () => ({}) },
        appointments: { type: Array,  default: () => [] },
        purchases:    { type: Array,  default: () => [] },
    },

    data() {
        return { tab: 'upcoming' }
    },

    computed: {
        /** Un client bloqué garde son espace mais ne peut plus réserver. */
        canBook() {
            return Boolean(this.$page.props.auth.permissions.book)
        },

        isBlocked() {
            return Boolean(this.$page.props.auth.user?.is_blocked)
        },

        /**
         * Séparation par la date réelle et non par le statut : un rendez-vous
         * annulé la semaine dernière appartient au passé, quel que soit
         * l'endroit où son statut s'est arrêté.
         */
        upcoming() {
            return this.appointments.filter((a) => new Date(a.starts_at) >= new Date())
        },
        past() {
            return this.appointments.filter((a) => new Date(a.starts_at) < new Date())
        },

        tabs() {
            return [
                { key: 'upcoming',  label: 'À venir',   count: this.upcoming.length },
                { key: 'past',      label: 'Historique', count: this.past.length },
                { key: 'purchases', label: 'Mes achats', count: this.purchases.length },
            ]
        },

        purchasesTotal() {
            return this.purchases.reduce((sum, p) => sum + p.total, 0)
        },
    },

    methods: {
        formatDate,
        formatDateLong,
        formatTime,
        money,
        statusLabel,
        statusClass,

        cancel(id) {
            if (confirm('Annuler ce rendez-vous ?')) {
                router.post(route('appointments.cancel', id), {}, { preserveScroll: true })
            }
        },

        acceptProposal(id) {
            router.post(route('proposals.accept', id), {}, { preserveScroll: true })
        },

        refuseProposal(id) {
            router.post(route('proposals.refuse', id), {}, { preserveScroll: true })
        },
    },
}
</script>

<template>
    <div class="shell section">
        <div class="page-head">
            <h1 class="title-glow">Mon espace</h1>

            <Link v-if="canBook" :href="route('booking.services')" class="btn btn--book">
                Réserver
            </Link>
        </div>

        <p class="account-locked" v-if="isBlocked">
            La réservation en ligne n'est plus disponible sur ce compte.
            Contactez le studio pour rétablir votre accès.
        </p>

        <!-- Fidélité : le palier et ce qu'il reste avant le suivant -->
        <section class="card admin-card" v-if="loyalty.label">
            <h2 class="admin-card-title">Fidélité</h2>

            <p class="kpi-value" :style="{ color: loyalty.color }">{{ loyalty.label }}</p>
            <p class="row-meta">{{ loyalty.visits }} visite(s) au studio</p>

            <div class="month-bar" style="width: 100%; height: 6px; margin: 14px 0 8px">
                <span :style="{ width: loyalty.progress + '%', background: loyalty.color }"></span>
            </div>

            <p class="row-meta" v-if="loyalty.remaining">
                Encore {{ loyalty.remaining }} visite(s) avant le palier {{ loyalty.next_label }}.
            </p>
            <p class="row-meta" v-else>Palier le plus élevé atteint.</p>
        </section>

        <section class="card admin-card" v-if="debt.amount > 0"
                 style="border-color: var(--danger-border)">
            <h2 class="admin-card-title" style="color: var(--danger)">Dette en cours</h2>
            <p class="kpi-value" style="color: var(--danger)">{{ money(debt.amount) }}</p>
            <p class="row-meta" v-if="debt.note">{{ debt.note }}</p>
            <p class="row-meta">À régler au studio lors de votre prochain passage.</p>
        </section>

        <!-- Onglets : les rendez-vous à venir, ceux qui sont passés et les
             achats sont trois lectures distinctes, pas une longue page. -->
        <div class="cms-tabs" style="margin-bottom: 18px">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                class="cms-tab"
                :class="{ 'is-active': tab === t.key }"
                @click="tab = t.key"
            >
                <span>{{ t.label }}</span>
                <span class="cms-tab-count">{{ t.count }}</span>
            </button>
        </div>

        <section class="card admin-card" v-show="tab === 'upcoming'">
            <h2 class="admin-card-title">Rendez-vous à venir</h2>

            <div class="row-list" v-if="upcoming.length">
                <div class="row-item" v-for="a in upcoming" :key="a.id">
                    <div class="row-main">
                        <p class="row-title">{{ formatDateLong(a.starts_at) }} · {{ formatTime(a.starts_at) }}</p>
                        <p class="row-meta">
                            {{ a.service }}
                            <span v-if="a.includes">({{ a.includes }})</span>
                            · {{ money(a.total_due) }}
                        </p>
                        <p class="row-meta" v-if="a.admin_note">Message du barber : {{ a.admin_note }}</p>

                        <!-- Contre-proposition : le client choisit un créneau -->
                        <div v-if="a.status === 'counter_proposed' && a.proposals.length" style="margin-top: 12px">
                            <p class="row-meta">Le barber vous propose :</p>
                            <div class="row-actions" style="margin-top: 8px">
                                <button
                                    v-for="p in a.proposals"
                                    :key="p.id"
                                    type="button"
                                    class="btn btn--book btn--sm"
                                    @click="acceptProposal(p.id)"
                                >{{ formatDateLong(p.starts_at) }} · {{ formatTime(p.starts_at) }}</button>

                                <button type="button" class="btn btn--ghost btn--sm"
                                        @click="refuseProposal(a.id)">Aucun ne convient</button>
                            </div>
                        </div>
                    </div>

                    <span class="badge" :class="statusClass(a.status)">{{ statusLabel(a.status) }}</span>

                    <button v-if="a.is_cancellable" type="button" class="btn btn--ghost btn--sm"
                            @click="cancel(a.id)">Annuler</button>
                </div>
            </div>

            <div v-else class="empty-state">
                <p>Aucun rendez-vous à venir.</p>
            </div>
        </section>

        <section class="card admin-card" v-show="tab === 'past'">
            <h2 class="admin-card-title">Rendez-vous passés</h2>

            <div class="row-list">
                <div class="row-item" v-for="a in past" :key="a.id">
                    <div class="row-main">
                        <p class="row-title">{{ formatDateLong(a.starts_at) }}</p>
                        <p class="row-meta">{{ a.service }} · {{ money(a.service_price) }}</p>
                    </div>
                    <span class="badge" :class="statusClass(a.status)">{{ statusLabel(a.status) }}</span>
                </div>
            </div>

            <p v-if="!past.length" class="empty-state">Aucun rendez-vous passé.</p>
        </section>

        <!-- Achats réalisés au studio -->
        <section class="card admin-card" v-show="tab === 'purchases'">
            <h2 class="admin-card-title">Mes achats</h2>

            <template v-if="purchases.length">
                <div class="row-list">
                    <div class="row-item" v-for="p in purchases" :key="p.id">
                        <div class="row-main">
                            <p class="row-title">{{ p.product }}</p>
                            <p class="row-meta">
                                {{ formatDate(p.sold_at) }}
                                <span v-if="p.quantity > 1"> · ×{{ p.quantity }}</span>
                            </p>
                        </div>
                        <span class="service-price" style="font-size: 17px">{{ money(p.total) }}</span>
                    </div>
                </div>

                <p class="row-meta" style="text-align: right; margin-top: 14px">
                    Total : <strong>{{ money(purchasesTotal) }}</strong>
                </p>
            </template>

            <div v-else class="empty-state">
                <p>Aucun achat enregistré.</p>
                <p style="font-size: 13px; margin-top: 6px">
                    Les produits Only Sayajin Store sont vendus exclusivement au studio.
                </p>
            </div>
        </section>
    </div>
</template>
