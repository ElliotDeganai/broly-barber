<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import SmartImage from '@/Components/SmartImage.vue'
import { router, useForm } from '@inertiajs/vue3'
import { formatDateLong, money, todayInStudioTz } from '@/date'

/**
 * Ventes — saisie au comptoir.
 *
 * Les produits sont des vignettes : trois interactions pour enregistrer une
 * vente au lieu de six. Au-delà de huit produits, le serveur bascule sur une
 * liste déroulante, sinon la grille occuperait tout l'écran sur mobile.
 */
export default {
    layout: AdminLayout,
    components: { Icon, SmartImage },

    props: {
        from:        { type: String, required: true },
        to:          { type: String, required: true },
        preset:      { type: String, default: 'month' },
        search:      { type: String, default: null },
        days:        { type: Array,  default: () => [] },
        periodTotal: { type: Number, default: 0 },
        periodCount: { type: Number, default: 0 },
        byProduct:   { type: Array,  default: () => [] },
        products:   { type: Array,  default: () => [] },
        useTiles:   { type: Boolean, default: true },
        clients:    { type: Array,  default: () => [] },
    },

    data() {
        return {
            q: this.search || '',
            local: { from: this.from, to: this.to },
            form: useForm({
                product_id: '',
                user_id: '',
                quantity: 1,
                sold_at: todayInStudioTz(),
            }),
        }
    },

    computed: {
        selected() {
            return this.products.find((p) => String(p.id) === String(this.form.product_id))
        },

        total() {
            return (this.selected?.price || 0) * (this.form.quantity || 0)
        },

        /** Mêmes raccourcis que l'écran des statistiques. */
        presets() {
            return [
                { key: 'month',   label: 'Ce mois' },
                { key: 'quarter', label: '3 mois' },
                { key: 'year',    label: 'Année' },
                { key: null,      label: 'Personnalisé' },
            ]
        },

        periodLabel() {
            const options = { day: 'numeric', month: 'short', year: 'numeric' }

            return `${new Date(this.from).toLocaleDateString('fr-FR', options)} → ${new Date(this.to).toLocaleDateString('fr-FR', options)}`
        },

        maxProduct() {
            return Math.max(...this.byProduct.map((p) => p.total), 1)
        },
    },

    methods: {
        money,
        formatDateLong,

        pick(product) {
            // Un second appui désélectionne : c'est le geste attendu sur une
            // grille de vignettes, et cela évite un bouton « annuler ».
            this.form.product_id = this.form.product_id === product.id ? '' : product.id
        },

        step(delta) {
            this.form.quantity = Math.min(99, Math.max(1, (this.form.quantity || 1) + delta))
        },

        save() {
            if (!this.form.product_id) return

            this.form.post(route('admin.sales.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    // Le produit et le client sont réinitialisés, pas la date :
                    // on saisit souvent plusieurs ventes du même jour.
                    this.form.product_id = ''
                    this.form.user_id = ''
                    this.form.quantity = 1
                },
            })
        },

        remove(id) {
            if (confirm('Supprimer cette vente ?')) {
                router.delete(route('admin.sales.destroy', id), { preserveScroll: true })
            }
        },

        /** Un seul point d'entrée : chaque filtre conserve les autres. */
        applyFilters(changes = {}) {
            const params = {
                preset: this.preset || undefined,
                from:   this.local.from,
                to:     this.local.to,
                q:      this.q || undefined,
                ...changes,
            }

            // Un raccourci calcule ses dates côté serveur : les transmettre
            // figerait la période au lieu de la recalculer.
            if (params.preset) {
                delete params.from
                delete params.to
            }

            Object.keys(params).forEach((k) => {
                if (!params[k]) delete params[k]
            })

            router.get(route('admin.sales.index'), params, {
                preserveScroll: true,
                preserveState: true,
            })
        },

        applyPreset(key) {
            this.applyFilters({ preset: key || undefined, from: this.local.from, to: this.local.to })
        },

        applySearch() {
            this.applyFilters()
        },

        dayLabel(date) {
            const today = todayInStudioTz()
            const yesterday = new Date(Date.now() - 86400000).toLocaleDateString('sv-SE')

            if (date === today) return "Aujourd'hui"
            if (date === yesterday) return 'Hier'

            return formatDateLong(date)
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Ventes</h1>
    <p class="admin-sub">
        Produits vendus au studio. Le rattachement à un client est facultatif,
        mais il alimente son historique.
    </p>

    <section class="card admin-card">
        <h2 class="admin-card-title">Enregistrer une vente</h2>

        <!-- Grille de vignettes tant que le catalogue reste court -->
        <div class="tile-grid" v-if="useTiles && products.length">
            <button
                v-for="p in products"
                :key="p.id"
                type="button"
                class="tile"
                :class="{ 'is-picked': form.product_id === p.id }"
                :aria-pressed="form.product_id === p.id"
                @click="pick(p)"
            >
                <SmartImage :image="p.image" :alt="p.name" sizes="140px" />
                <span class="tile-body">
                    <span class="tile-name">{{ p.name }}</span>
                    <span class="tile-price">{{ money(p.price) }}</span>
                </span>
            </button>
        </div>

        <label class="field" v-else>
            <span class="field-label">Produit</span>
            <select v-model="form.product_id">
                <option value="">Choisir…</option>
                <option v-for="p in products" :key="p.id" :value="p.id">
                    {{ p.name }} — {{ money(p.price) }}
                </option>
            </select>
        </label>

        <p class="field-error" v-if="form.errors.product_id">{{ form.errors.product_id }}</p>

        <div class="sale-form">
            <div class="field">
                <span class="field-label">Quantité</span>
                <div class="stepper">
                    <button type="button" aria-label="Retirer un" @click="step(-1)">−</button>
                    <span>{{ form.quantity }}</span>
                    <button type="button" aria-label="Ajouter un" @click="step(1)">+</button>
                </div>
            </div>

            <label class="field">
                <span class="field-label">Client (facultatif)</span>
                <select v-model="form.user_id">
                    <option value="">Non rattaché</option>
                    <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Date</span>
                <input type="date" v-model="form.sold_at">
            </label>
        </div>

        <!-- Le montant est inscrit dans le bouton : on confirme ce qu'on encaisse -->
        <button
            type="button"
            class="btn btn--solid btn--block"
            :disabled="form.processing || !form.product_id"
            @click="save"
        >
            {{ form.product_id ? `Encaisser ${money(total)}` : 'Choisissez un produit' }}
        </button>
    </section>

    <section class="card admin-card">
        <h2 class="admin-card-title">Historique</h2>

        <!-- Mêmes raccourcis de période que l'écran des statistiques -->
        <div class="cms-tabs" style="margin-bottom: 14px">
            <button
                v-for="p in presets"
                :key="p.key || 'custom'"
                type="button"
                class="cms-tab"
                :class="{ 'is-active': preset === p.key }"
                @click="applyPreset(p.key)"
            >{{ p.label }}</button>
        </div>

        <div class="filter-bar" v-if="!preset">
            <label class="field">
                <span class="field-label">Du</span>
                <input type="date" v-model="local.from">
            </label>
            <label class="field">
                <span class="field-label">Au</span>
                <input type="date" v-model="local.to">
            </label>
            <button type="button" class="btn btn--solid btn--sm" @click="applyFilters()">Appliquer</button>
        </div>

        <label class="field">
            <span class="field-label">Rechercher un produit</span>
            <input type="text" v-model="q" placeholder="Nom du produit" @keyup.enter="applySearch">
        </label>

        <p class="row-meta" style="margin-bottom: 14px">{{ periodLabel }}</p>

        <template v-if="days.length">
            <div v-for="day in days" :key="day.date" class="sale-day">
                <p class="sale-day-head">
                    <span>{{ dayLabel(day.date) }}</span>
                    <span>{{ money(day.total) }}</span>
                </p>

                <div class="row-item" v-for="sale in day.sales" :key="sale.id">
                    <div class="row-main">
                        <p class="row-title">
                            {{ sale.product }}
                            <span class="muted" v-if="sale.quantity > 1"> ×{{ sale.quantity }}</span>
                        </p>
                        <p class="row-meta" v-if="sale.client">{{ sale.client }}</p>
                    </div>

                    <span class="service-price" style="font-size: 16px">{{ money(sale.total) }}</span>

                    <button type="button" class="btn-icon btn-icon--danger" aria-label="Supprimer"
                            @click="remove(sale.id)">
                        <Icon name="trash" :size="16" />
                    </button>
                </div>
            </div>

            <p class="sale-total">
                <span>{{ periodCount }} vente(s) sur la période</span>
                <strong>{{ money(periodTotal) }}</strong>
            </p>
        </template>

        <p v-else class="empty-state">Aucune vente sur cette période.</p>
    </section>

    <!-- Quel produit se vend réellement : l'information que l'historique
         chronologique ne donne pas. -->
    <section class="card admin-card" v-if="byProduct.length">
        <h2 class="admin-card-title">Par produit</h2>

        <div class="rank" v-for="p in byProduct" :key="p.name">
            <span class="rank-name">{{ p.name }}</span>
            <span class="rank-bar">
                <span :style="{ width: (p.total / maxProduct * 100) + '%' }"></span>
            </span>
            <span class="rank-count">×{{ p.quantity }}</span>
            <span class="rank-value">{{ money(p.total) }}</span>
        </div>
    </section>
</template>
