<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { money } from '@/date'

/** Étape 1 du tunnel : choix de la prestation, avec ce qui est compris. */
export default {
    layout: AppLayout,
    components: { Link },

    props: {
        services:  { type: Array,  default: () => [] },
        lateOffer: { type: Object, default: () => ({}) },
    },

    methods: {
        money,
        imageUrl(service) {
            return service.image_path ? `/storage/${service.image_path}` : null
        },
    },
}
</script>

<template>
    <div class="shell section">
        <p class="badge badge--green">Étape 1 sur 3</p>
        <h1 class="title-glow" style="margin: 14px 0 8px">Choisissez votre prestation</h1>
        <p class="lede" style="margin-bottom: 26px">
            Un créneau après {{ lateOffer.hour }}h est facturé
            ×{{ lateOffer.multiplier }} — le tarif s'affiche avant validation.
        </p>

        <div class="service-list">
            <article v-for="service in services" :key="service.id" class="card service-card reveal"
                     style="padding: 16px">
                <div class="service-visual skeleton" v-if="imageUrl(service)">
                    <img :src="imageUrl(service)" :alt="service.name" loading="lazy" decoding="async">
                </div>

                <div class="service-head">
                    <span class="service-name">
                        {{ service.name }}
                        <span class="service-includes" v-if="service.includes">({{ service.includes }})</span>
                    </span>
                    <span class="service-price">{{ money(service.price) }}</span>
                </div>

                <p class="service-desc">
                    {{ service.description || (service.has_restructuration
                        ? 'Restructuration comprise.' : 'Sans restructuration.') }}
                    Durée {{ service.duration_min }} min.
                </p>

                <Link :href="route('booking.slots', service.slug)" class="btn btn--book btn--block">
                    Choisir ce créneau
                </Link>
            </article>
        </div>
    </div>
</template>
