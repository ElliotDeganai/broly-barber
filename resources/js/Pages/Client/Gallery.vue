<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import { router } from '@inertiajs/vue3'

/**
 * Galerie en carrousel — la note de développement écarte explicitement la
 * mosaïque. Le client fait défiler puis ouvre une photo pour l'observer.
 */
export default {
    layout: AppLayout,
    components: { Icon },

    props: {
        content:  { type: Object, default: () => ({}) },
        items:    { type: Array,  default: () => [] },
        services: { type: Array,  default: () => [] },
        filter:   { type: Number, default: null },
    },

    data() {
        return {
            lightboxIndex: null,
            touchStartX: null,
        }
    },

    computed: {
        header() {
            return this.content.header || {}
        },
        currentPhoto() {
            return this.lightboxIndex !== null ? this.items[this.lightboxIndex] : null
        },
    },

    mounted() {
        this._keyHandler = (e) => {
            if (this.lightboxIndex === null) return

            if (e.key === 'Escape') this.closeLightbox()
            if (e.key === 'ArrowLeft') this.prevPhoto()
            if (e.key === 'ArrowRight') this.nextPhoto()
        }

        document.addEventListener('keydown', this._keyHandler)
    },

    beforeUnmount() {
        document.removeEventListener('keydown', this._keyHandler)
        document.body.style.overflow = ''
    },

    methods: {
        openLightbox(index) {
            this.lightboxIndex = index
            // Sans ce verrou, la page défile derrière la photo ouverte
            document.body.style.overflow = 'hidden'
        },

        closeLightbox() {
            this.lightboxIndex = null
            document.body.style.overflow = ''
        },

        /** Défilement circulaire : après la dernière photo, retour à la première. */
        prevPhoto() {
            this.lightboxIndex = (this.lightboxIndex - 1 + this.items.length) % this.items.length
        },

        nextPhoto() {
            this.lightboxIndex = (this.lightboxIndex + 1) % this.items.length
        },

        /** Défilement du carrousel d'une vignette, quelle que soit sa largeur réelle. */
        scrollBy(direction) {
            const rail = this.$refs.rail
            if (!rail) return

            const card = rail.querySelector('figure')
            rail.scrollBy({ left: direction * ((card?.offsetWidth || 260) + 14), behavior: 'smooth' })
        },

        onTouchStart(event) {
            this.touchStartX = event.changedTouches[0].clientX
        },

        onTouchEnd(event) {
            if (this.touchStartX === null) return

            // 50 px : en dessous, c'est un appui et non un glissement
            const delta = event.changedTouches[0].clientX - this.touchStartX

            if (delta < -50) this.nextPhoto()
            if (delta > 50) this.prevPhoto()

            this.touchStartX = null
        },

        setFilter(id) {
            router.get(route('gallery'), id ? { service_id: id } : {}, { preserveScroll: true })
        },
    },
}
</script>

<template>
    <div class="shell section">
        <h1 class="title-glow center" style="margin-bottom: 26px">{{ header.title || 'Galerie' }}</h1>

        <div class="footer-links" v-if="services.length" style="margin-bottom: 22px">
            <button type="button" class="badge" :class="filter ? 'badge--green' : 'badge--yellow'"
                    style="border: none" @click="setFilter(null)">Tout</button>
            <button
                v-for="service in services"
                :key="service.id"
                type="button"
                class="badge"
                :class="filter === service.id ? 'badge--yellow' : 'badge--green'"
                style="border: none"
                @click="setFilter(service.id)"
            >{{ service.name }}</button>
        </div>

        <template v-if="items.length">
            <div class="carousel" ref="rail">
                <figure
                    v-for="(item, i) in items"
                    :key="item.id"
                    class="skeleton"
                    @click="openLightbox(i)"
                >
                    <img :src="item.url" :alt="item.alt || 'Réalisation du studio'"
                         loading="lazy" decoding="async">
                </figure>
            </div>

            <div class="carousel-nav">
                <button type="button" aria-label="Photos précédentes" @click="scrollBy(-1)">
                    <Icon name="left" :size="22" />
                </button>
                <span class="carousel-count">{{ items.length }} photos</span>
                <button type="button" aria-label="Photos suivantes" @click="scrollBy(1)">
                    <Icon name="right" :size="22" />
                </button>
            </div>
        </template>

        <p v-else class="muted center">Aucune photo publiée pour l'instant.</p>
    </div>

    <!-- Plein écran : défilement aux flèches, au clavier ou au glissement -->
    <div
        v-if="currentPhoto"
        class="lightbox"
        role="dialog"
        aria-modal="true"
        aria-label="Photo en plein écran"
        @click="closeLightbox"
        @touchstart="onTouchStart"
        @touchend="onTouchEnd"
    >
        <button type="button" class="lightbox-close" aria-label="Fermer" @click.stop="closeLightbox">
            <Icon name="close" :size="22" />
        </button>

        <button v-if="items.length > 1" type="button" class="lightbox-nav-btn"
                aria-label="Photo précédente" @click.stop="prevPhoto">
            <Icon name="left" :size="24" />
        </button>

        <figure class="lightbox-figure" @click.stop>
            <img :src="currentPhoto.url" :alt="currentPhoto.alt || 'Réalisation du studio'">
            <figcaption v-if="currentPhoto.alt">{{ currentPhoto.alt }}</figcaption>
        </figure>

        <button v-if="items.length > 1" type="button" class="lightbox-nav-btn"
                aria-label="Photo suivante" @click.stop="nextPhoto">
            <Icon name="right" :size="24" />
        </button>
    </div>
</template>
