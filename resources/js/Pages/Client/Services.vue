<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

/**
 * Prestations. Chaque carte annonce ce qui est compris — avec ou sans
 * restructuration — pour que le client comprenne le tarif avant de réserver.
 */
export default {
    layout: AppLayout,
    components: { Link },

    props: {
        content:   { type: Object, default: () => ({}) },
        services:  { type: Array,  default: () => [] },
        lateOffer: { type: Object, default: () => ({}) },
    },

    computed: {
        header() {
            return this.content.header || {}
        },
        user() {
            return this.$page.props.auth.user
        },
        bookingHref() {
            if (!this.user) return route('client.login')
            if (this.$page.props.auth.permissions.manage_content) return route('admin.appointments.index')

            return route('booking.services')
        },
    },

    methods: {
        price(value) {
            return `${Number(value).toFixed(0)}€`
        },
        serviceHref(service) {
            return this.user && this.$page.props.auth.permissions.book
                ? route('booking.slots', service.slug)
                : this.bookingHref
        },
        imageUrl(service) {
            return service.image_path ? `/storage/${service.image_path}` : null
        },
    },
}
</script>

<template>
    <div class="shell section">
        <h1 class="title-glow center" style="margin-bottom: 30px">
            {{ header.title || 'Prestations' }}
        </h1>
        <p class="lede center" v-if="header.subtitle" style="margin-bottom: 30px">{{ header.subtitle }}</p>

        <div class="service-list">
            <article v-for="service in services" :key="service.id" class="service-card reveal">
                <div class="service-visual skeleton">
                    <img v-if="imageUrl(service)" :src="imageUrl(service)" :alt="service.name"
                         loading="lazy" decoding="async">
                </div>

                <div class="service-head">
                    <span class="service-name">
                        {{ service.name }}
                        <span class="service-includes" v-if="service.includes">({{ service.includes }})</span>
                    </span>
                    <span class="service-price">{{ price(service.price) }}</span>
                </div>

                <p class="service-desc" v-if="service.description">{{ service.description }}</p>
                <p class="service-desc" v-else>
                    {{ service.has_restructuration
                        ? 'Restructuration comprise.'
                        : 'Sans restructuration.' }}
                    Durée {{ service.duration_min }} min.
                </p>

                <Link :href="serviceHref(service)" class="btn btn--book btn--block">Réserver</Link>
            </article>
        </div>

        <!-- Offre de dernière minute : tarif doublé après l'heure de bascule -->
        <section class="late-offer card reveal" style="margin-top: 34px" v-if="lateOffer.multiplier">
            <div class="late-offer-visual skeleton" v-if="content.late && content.late.image">
                <img :src="content.late.image" alt="" loading="lazy" decoding="async">
            </div>
            <div class="late-offer-body">
                <p class="late-offer-title">
                    {{ content.late && content.late.label || 'Last minute' }} ·
                    Après {{ lateOffer.hour }}h
                </p>
                <p class="late-offer-multiplier">×{{ lateOffer.multiplier }}</p>
                <p class="muted" style="font-size: 13px">
                    Les créneaux réservés après {{ lateOffer.hour }}h sont facturés au double du tarif.
                </p>
                <Link :href="bookingHref" class="btn btn--book btn--sm">Réserver</Link>
            </div>
        </section>
    </div>
</template>
