<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { Link, router } from '@inertiajs/vue3'
import { formatTime, formatDateTime, money } from '@/date'
import { statusLabel, statusClass } from '@/status'

/** Ce que le barbier doit voir en ouvrant l'application. */
export default {
    layout: AdminLayout,
    components: { Icon, Link },

    props: {
        stats:               { type: Object, default: () => ({}) },
        todayAppointments:   { type: Array,  default: () => [] },
        pendingAppointments: { type: Array,  default: () => [] },
    },

    methods: {
        formatTime,
        formatDateTime,
        money,
        statusLabel,
        statusClass,

        confirm(id) {
            router.post(route('admin.appointments.confirm', id), {}, { preserveScroll: true })
        },
        refuse(id) {
            if (confirm('Refuser cette demande ?')) {
                router.post(route('admin.appointments.refuse', id), {}, { preserveScroll: true })
            }
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Tableau de bord</h1>
    <p class="admin-sub" v-if="stats.pending">
        {{ stats.pending }} demande(s) en attente de votre réponse.
    </p>
    <p class="admin-sub" v-else>Aucune demande en attente.</p>

    <div class="kpi-grid">
        <div class="card kpi">
            <p class="kpi-label">CA du mois</p>
            <p class="kpi-value">{{ money(stats.revenue_month) }}</p>
            <p class="kpi-note">Prestations et boutique</p>
        </div>
        <div class="card kpi">
            <p class="kpi-label">En attente</p>
            <p class="kpi-value">{{ stats.pending }}</p>
        </div>
        <div class="card kpi">
            <p class="kpi-label">Aujourd'hui</p>
            <p class="kpi-value">{{ stats.today }}</p>
            <p class="kpi-note">rendez-vous</p>
        </div>
        <div class="card kpi">
            <p class="kpi-label">Clients</p>
            <p class="kpi-value">{{ stats.clients }}</p>
        </div>
        <div class="card kpi">
            <p class="kpi-label">En dette</p>
            <p class="kpi-value">{{ stats.in_debt }}</p>
        </div>
        <div class="card kpi">
            <p class="kpi-label">Bloqués</p>
            <p class="kpi-value">{{ stats.blocked }}</p>
        </div>
    </div>

    <section class="card admin-card">
        <h2 class="admin-card-title">Journée en cours</h2>

        <div class="row-list" v-if="todayAppointments.length">
            <div class="row-item" v-for="a in todayAppointments" :key="a.id">
                <div class="row-main">
                    <p class="row-title">{{ formatTime(a.starts_at) }} — {{ a.client }}</p>
                    <p class="row-meta">{{ a.service }} · {{ a.duration_min }} min</p>
                </div>
                <span class="badge" :class="statusClass(a.status)">{{ statusLabel(a.status) }}</span>
            </div>
        </div>

        <p v-else class="empty-state">Aucun rendez-vous aujourd'hui.</p>
    </section>

    <section class="card admin-card">
        <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 14px">
            <h2 class="admin-card-title" style="margin-bottom: 0">Demandes en attente</h2>
            <Link :href="route('admin.appointments.index', { status: 'pending' })"
                  style="color: var(--green); font-size: 13px">
                Tout voir
            </Link>
        </div>

        <div class="row-list" v-if="pendingAppointments.length" style="margin-top: 16px">
            <div class="row-item" v-for="a in pendingAppointments" :key="a.id">
                <div class="row-main">
                    <p class="row-title">{{ formatDateTime(a.starts_at) }} — {{ a.client }}</p>
                    <p class="row-meta">
                        {{ a.service }}
                        <span v-if="a.debt > 0" style="color: var(--danger)">
                            · dette {{ money(a.debt) }}
                        </span>
                    </p>
                </div>
                <div class="row-actions">
                    <button type="button" class="btn btn--solid btn--sm" @click="confirm(a.id)">Confirmer</button>
                    <button type="button" class="btn btn--ghost btn--sm" @click="refuse(a.id)">Refuser</button>
                </div>
            </div>
        </div>

        <p v-else class="empty-state" style="margin-top: 16px">Aucune demande en attente.</p>
    </section>
</template>
