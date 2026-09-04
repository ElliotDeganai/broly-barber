<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { Link, router } from '@inertiajs/vue3'
import { money } from '@/date'

export default {
    layout: AdminLayout,
    components: { Icon, Link },

    props: {
        products:   { type: Array, default: () => [] },
        categories: { type: Array, default: () => [] },
    },

    methods: {
        money,
        remove(product) {
            if (confirm(`Supprimer « ${product.name} » ?`)) {
                router.delete(route('admin.products.destroy', product.id), { preserveScroll: true })
            }
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Only Sayajin Store</h1>
    <p class="admin-sub">
        Vitrine seule : aucun paiement en ligne. Les ventes se saisissent depuis
        l'onglet Ventes.
    </p>

    <Link :href="route('admin.products.create')" class="btn btn--solid btn--sm" style="margin-bottom: 20px">
        <Icon name="plus" :size="16" /> Nouveau produit
    </Link>

    <section class="card admin-card">
        <div class="row-list" v-if="products.length">
            <div class="row-item" v-for="product in products" :key="product.id">
                <div class="thumb" style="flex: 0 0 56px; width: 56px" v-if="product.photo">
                    <img :src="product.photo" :alt="product.name" loading="lazy" decoding="async">
                </div>

                <div class="row-main">
                    <p class="row-title">{{ product.name }}</p>
                    <p class="row-meta">
                        <span v-if="product.category">{{ product.category }} · </span>
                        {{ money(product.price) }} · {{ product.sales_count }} vente(s)
                    </p>
                </div>

                <span class="badge" :class="product.is_published ? 'badge--orange' : 'badge--danger'">
                    {{ product.is_published ? 'Publié' : 'Masqué' }}
                </span>

                <div class="row-actions">
                    <Link :href="route('admin.products.edit', product.id)" class="btn-icon" aria-label="Modifier">
                        <Icon name="pencil" :size="16" />
                    </Link>
                    <button type="button" class="btn-icon btn-icon--danger" aria-label="Supprimer"
                            @click="remove(product)">
                        <Icon name="trash" :size="16" />
                    </button>
                </div>
            </div>
        </div>

        <p v-else class="empty-state">Aucun produit.</p>
    </section>
</template>
