<script>
import AppLayout from '@/Layouts/AppLayout.vue'

/**
 * Only Sayajin Store. Vitrine seule : aucun panier, aucun paiement.
 * La mention « disponible uniquement au studio » est affichée en orange,
 * couleur réservée à la boutique par la charte.
 */
export default {
    layout: AppLayout,

    props: {
        content:    { type: Object, default: () => ({}) },
        categories: { type: Array,  default: () => [] },
    },

    computed: {
        header() {
            return this.content.header || {}
        },
    },

    methods: {
        price(value) {
            return `${Number(value).toFixed(0)}€`
        },
    },
}
</script>

<template>
    <div class="shell section">
        <h1 class="title-glow center">{{ header.title || 'Only Sayajin Store' }}</h1>

        <p class="shop-notice">
            {{ header.notice || 'Disponible uniquement au studio' }}
        </p>

        <section v-for="category in categories" :key="category.id" class="shop-group reveal">
            <h2 class="shop-group-title">{{ category.name }}</h2>
            <p class="shop-group-price" v-if="category.price">{{ price(category.price) }}</p>
            <p class="lede" v-if="category.description">{{ category.description }}</p>

            <div class="shop-grid">
                <article v-for="product in category.products" :key="product.id" class="shop-item skeleton">
                    <img v-if="product.photo" :src="product.photo" :alt="product.name"
                         loading="lazy" decoding="async">
                    <p class="shop-item-name">{{ product.name }}</p>
                </article>
            </div>
        </section>

        <p v-if="!categories.length" class="muted center">La boutique sera bientôt disponible.</p>
    </div>
</template>
