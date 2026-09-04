<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { router } from '@inertiajs/vue3'
import { money } from '@/date'

/**
 * Statistiques — rapport visuel.
 *
 * La tendance d'abord, les chiffres ensuite : un tableau seul oblige à lire
 * ligne par ligne pour reconstituer mentalement une courbe.
 */
export default {
    layout: AdminLayout,

    props: {
        from:        { type: String, required: true },
        to:          { type: String, required: true },
        preset:      { type: String, default: null },
        granularity: { type: String, default: 'month' },
        filters:     { type: Object, default: () => ({}) },
        revenue:     { type: Object, default: () => ({}) },
        volumes:     { type: Object, default: () => ({}) },
        series:      { type: Array,  default: () => [] },
        byService:   { type: Array,  default: () => [] },
        comparison:  { type: Object, default: () => ({}) },
        services:    { type: Array,  default: () => [] },
    },

    data() {
        return {
            // Tout, prestations seules ou boutique seule
            chartMode: 'all',
            local: {
                from: this.from,
                to: this.to,
                service_id: this.filters.service_id || '',
            },
        }
    },

    computed: {
        presets() {
            return [
                { key: 'month',   label: 'Ce mois' },
                { key: 'quarter', label: '3 mois' },
                { key: 'year',    label: 'Année' },
                { key: null,      label: 'Personnalisé' },
            ]
        },

        chartModes() {
            return [
                { key: 'all',      label: 'Tout' },
                { key: 'services', label: 'Prestations' },
                { key: 'products', label: 'Boutique' },
            ]
        },

        /**
         * Série affichée selon le mode. En vue filtrée, la barre ne porte plus
         * qu'une seule part : l'échelle s'y adapte, sinon les montants de la
         * boutique seraient écrasés par ceux des prestations.
         */
        chartSeries() {
            return this.series.map((row) => ({
                bucket:   row.bucket,
                services: this.chartMode === 'products' ? 0 : row.services,
                products: this.chartMode === 'services' ? 0 : row.products,
                value:    this.chartMode === 'all' ? row.total : row[this.chartMode],
            }))
        },

        /** Échelle des barres : la plus haute période vaut toute la hauteur. */
        maxTotal() {
            return Math.max(...this.chartSeries.map((s) => s.value), 1)
        },

        /**
         * Graduations de l'axe. Arrondies à un palier lisible — 50, 100, 250… —
         * plutôt qu'au maximum brut, qui donnerait « 1 987 € » en repère.
         */
        ticks() {
            const step = this.niceStep(this.maxTotal / 4)
            const top = Math.ceil(this.maxTotal / step) * step
            const out = []

            for (let v = top; v >= 0; v -= step) out.push(v)

            return out
        },

        /** Plafond réel de l'échelle : la plus haute graduation. */
        scaleMax() {
            return this.ticks[0] || 1
        },

        /** Au-delà, les montants au-dessus des barres se chevauchent. */
        showValues() {
            return this.chartSeries.length <= 14
        },

        maxService() {
            return Math.max(...this.byService.map((s) => s.amount), 1)
        },

        previous() {
            return this.comparison['n-1'] || null
        },

        /** Écart avec l'an dernier, en pourcentage. */
        variation() {
            if (!this.previous?.total) return null

            return Math.round((this.revenue.total - this.previous.total) / this.previous.total * 100)
        },

        /** Panier moyen par rendez-vous réalisé. */
        average() {
            if (!this.volumes.appointments) return null

            return this.revenue.services / this.volumes.appointments
        },
    },

    methods: {
        money,

        /** Hauteur d'une barre, rapportée au plafond de l'échelle. */
        barHeight(value) {
            return (value / this.scaleMax * 100).toFixed(1) + '%'
        },

        /** Arrondit un pas d'échelle à 1, 2, 5 ou 10 fois une puissance de dix. */
        niceStep(raw) {
            if (raw <= 0) return 1

            const magnitude = 10 ** Math.floor(Math.log10(raw))
            const normalized = raw / magnitude

            const factor = normalized <= 1 ? 1
                : normalized <= 2 ? 2
                : normalized <= 5 ? 5
                : 10

            return factor * magnitude
        },

        /** Montant compact au-dessus des barres : « 1,2 k€ » plutôt que « 1 240,00 € ». */
        shortMoney(value) {
            if (!value) return ''
            if (value >= 1000) return (value / 1000).toFixed(1).replace('.', ',') + ' k€'

            return Math.round(value) + ' €'
        },

        /**
         * Étiquette compacte sous chaque barre.
         * « 2026-09 » devient « sept. », « 2026-09-14 » devient « 14 ».
         */
        bucketLabel(bucket) {
            if (this.granularity === 'day') return bucket.slice(-2)
            if (this.granularity === 'week') return bucket.split('-W')[1]

            if (this.granularity === 'month') {
                const [y, m] = bucket.split('-')

                return new Date(y, m - 1, 1).toLocaleDateString('fr-FR', { month: 'short' })
            }

            return bucket
        },

        applyPreset(key) {
            router.get(route('admin.stats'), {
                preset: key || undefined,
                from: key ? undefined : this.local.from,
                to: key ? undefined : this.local.to,
                service_id: this.local.service_id || undefined,
            }, { preserveScroll: true })
        },

        applyCustom() {
            this.applyPreset(null)
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Statistiques</h1>
    <p class="admin-sub">
        Le chiffre d'affaires des prestations ne compte que les rendez-vous
        marqués comme réalisés.
    </p>

    <!-- Périodes courantes : la fenêtre consultée dans la quasi-totalité des cas -->
    <div class="cms-tabs" style="margin-bottom: 18px">
        <button
            v-for="p in presets"
            :key="p.key || 'custom'"
            type="button"
            class="cms-tab"
            :class="{ 'is-active': preset === p.key }"
            @click="applyPreset(p.key)"
        >{{ p.label }}</button>
    </div>

    <section class="card admin-card" v-if="!preset">
        <div class="filter-bar">
            <label class="field">
                <span class="field-label">Du</span>
                <input type="date" v-model="local.from">
            </label>
            <label class="field">
                <span class="field-label">Au</span>
                <input type="date" v-model="local.to">
            </label>
            <label class="field">
                <span class="field-label">Prestation</span>
                <select v-model="local.service_id">
                    <option value="">Toutes</option>
                    <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </label>
            <button type="button" class="btn btn--solid btn--sm" @click="applyCustom">Appliquer</button>
        </div>
    </section>

    <div class="stat-cards">
        <div class="card kpi">
            <p class="kpi-label">Total</p>
            <p class="kpi-value">{{ money(revenue.total) }}</p>
            <p class="kpi-note" v-if="variation !== null"
               :style="{ color: variation >= 0 ? 'var(--green)' : 'var(--danger)' }">
                {{ variation >= 0 ? '+' : '' }}{{ variation }} % vs N-1
            </p>
        </div>

        <div class="card kpi">
            <p class="kpi-label">Prestations</p>
            <p class="kpi-value">{{ money(revenue.services) }}</p>
            <p class="kpi-note">{{ volumes.appointments }} rendez-vous</p>
        </div>

        <div class="card kpi kpi--shop">
            <p class="kpi-label">Boutique</p>
            <p class="kpi-value">{{ money(revenue.products) }}</p>
            <p class="kpi-note">{{ volumes.products }} produit(s)</p>
        </div>

        <div class="card kpi" v-if="average">
            <p class="kpi-label">Panier moyen</p>
            <p class="kpi-value">{{ money(average) }}</p>
            <p class="kpi-note">par rendez-vous</p>
        </div>
    </div>

    <!-- Évolution : barres empilées, prestations et boutique -->
    <section class="card admin-card">
        <h2 class="admin-card-title">Évolution</h2>

        <!-- Filtre du graphe : tout, prestations seules ou boutique seule -->
        <div class="chart-modes">
            <button
                v-for="m in chartModes"
                :key="m.key"
                type="button"
                :class="{ 'is-active': chartMode === m.key }"
                @click="chartMode = m.key"
            >{{ m.label }}</button>
        </div>

        <template v-if="series.length">
            <div class="chart-wrap">
                <!-- Graduations : sans repère chiffré, une barre ne dit rien
                     de son montant. -->
                <div class="chart-scale">
                    <span v-for="t in ticks" :key="t">{{ shortMoney(t) }}</span>
                </div>

                <div class="chart-area">
                    <span
                        v-for="(t, i) in ticks"
                        :key="`line${t}`"
                        class="chart-line"
                        :style="{ bottom: (100 - (i / (ticks.length - 1)) * 100) + '%' }"
                    ></span>

                    <div class="chart">
                        <div
                            v-for="row in chartSeries"
                            :key="row.bucket"
                            class="chart-col"
                            :title="`${row.bucket} — ${money(row.value)}`"
                        >
                            <span class="chart-value" v-if="showValues && row.value">
                                {{ shortMoney(row.value) }}
                            </span>

                            <span class="chart-stack" :style="{ height: barHeight(row.value) }">
                                <!-- La boutique au-dessus : part la plus petite,
                                     elle disparaîtrait écrasée en dessous. -->
                                <span class="chart-shop" v-if="row.products"
                                      :style="{ flex: row.products }"></span>
                                <span class="chart-services" v-if="row.services"
                                      :style="{ flex: row.services }"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chart-axis">
                <span v-for="row in chartSeries" :key="row.bucket">{{ bucketLabel(row.bucket) }}</span>
            </div>

            <div class="chart-legend" v-if="chartMode === 'all'">
                <span><i class="dot dot--services"></i>Prestations</span>
                <span><i class="dot dot--shop"></i>Boutique</span>
            </div>
        </template>

        <p v-else class="empty-state">Aucune donnée sur cette période.</p>
    </section>

    <!-- Répartition par prestation -->
    <section class="card admin-card" v-if="byService.length">
        <h2 class="admin-card-title">Par prestation</h2>

        <div class="rank" v-for="row in byService" :key="row.name">
            <span class="rank-name">{{ row.name }}</span>
            <span class="rank-bar">
                <span :style="{ width: (row.amount / maxService * 100) + '%' }"></span>
            </span>
            <span class="rank-count">{{ row.count }}</span>
            <span class="rank-value">{{ money(row.amount) }}</span>
        </div>
    </section>

    <!-- Comparaison avec l'an dernier -->
    <section class="card admin-card" v-if="previous">
        <h2 class="admin-card-title">Comparaison annuelle</h2>

        <div class="table-scroll">
            <table class="admin-table">
                <thead>
                    <tr><th>Période</th><th>Prestations</th><th>Boutique</th><th>Total</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Période choisie</td>
                        <td>{{ money(revenue.services) }}</td>
                        <td>{{ money(revenue.products) }}</td>
                        <td>{{ money(revenue.total) }}</td>
                    </tr>
                    <tr>
                        <td class="muted">Même période N-1</td>
                        <td class="muted">{{ money(previous.services) }}</td>
                        <td class="muted">{{ money(previous.products) }}</td>
                        <td class="muted">{{ money(previous.total) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
