<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import SmartImage from '@/Components/SmartImage.vue'
import Reveal from '@/Components/Reveal.vue'
import revealOnce from '@/revealOnce'
import { Link } from '@inertiajs/vue3'
import { formatDateLong, formatTime, money } from '@/date'

/**
 * Accueil — page vitrine complète, conforme au wireframe.
 *
 * Bandeau, prestations, produits, galerie et FAQ s'enchaînent : un visiteur
 * doit pouvoir tout comprendre sans naviguer. Chaque section renvoie vers sa
 * page dédiée pour le détail.
 */
export default {
    layout: (h, page) => h(AppLayout, { flush: true }, () => page),

    components: { Icon, SmartImage, Reveal, Link },
    mixins: [revealOnce],

    props: {
        content:   { type: Object, default: () => ({}) },
        services:  { type: Array,  default: () => [] },
        products:  { type: Array,  default: () => [] },
        gallery:   { type: Array,  default: () => [] },
        faqs:      { type: Array,  default: () => [] },
        lateOffer: { type: Object, default: () => ({}) },
        nextSlot:  { type: Object, default: null },
    },

    data() {
        return {
            openFaq: null,
            lightboxIndex: null,
        }
    },

    computed: {
        hero()     { return this.content.hero || {} },
        settings() { return this.$page.props.settings || {} },
        user()     { return this.$page.props.auth.user },

        text() {
            // Raccourci de lecture : text.services_title plutôt que content.services.title
            return {
                services_title:  this.content.services?.title  || 'Prestations',
                products_title:  this.content.products?.title  || 'Produits',
                products_notice: this.content.products?.notice || 'Disponible uniquement au studio',
                gallery_title:   this.content.gallery?.title   || 'Galerie',
                faq_title:       this.content.faq?.title       || 'FAQ',
            }
        },

        /**
         * Un admin n'a pas la permission de réserver : l'envoyer vers la
         * connexion le renverrait aussitôt en arrière.
         */
        bookingHref() {
            if (!this.user) return route('client.login')
            if (this.$page.props.auth.permissions.manage_content) return route('admin.appointments.index')

            return route('booking.services')
        },

        currentPhoto() {
            return this.lightboxIndex !== null ? this.gallery[this.lightboxIndex] : null
        },


        /** Les trois étapes du parcours, reprises de la première version. */
        steps() {
            return [
                {
                    n: 1,
                    icon: 'scissors',
                    title: 'Choisissez',
                    text: 'La prestation et le créneau qui vous conviennent, parmi les disponibilités réelles du studio.',
                },
                {
                    n: 2,
                    icon: 'clock',
                    title: 'Le barber valide',
                    text: `Votre demande est confirmée sous ${this.responseHours} h. Le créneau n'est réservé qu'après sa réponse.`,
                },
                {
                    n: 3,
                    icon: 'check',
                    title: 'Vous venez',
                    text: "L'adresse vous est communiquée à la confirmation. Le règlement s'effectue au studio.",
                },
            ]
        },

        responseHours() {
            return this.settings.response_hours || 24
        },

        socials() {
            return [
                { key: 'tiktok',    setting: 'tiktok_url',    icon: 'tiktok',    label: 'TikTok' },
                { key: 'instagram', setting: 'instagram_url', icon: 'instagram', label: 'Instagram' },
            ].filter((s) => this.settings[s.setting])
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
        money,
        formatDateLong,
        formatTime,

        serviceHref(service) {
            return this.user && this.$page.props.auth.permissions.book
                ? route('booking.slots', service.slug)
                : this.bookingHref
        },

        toggleFaq(id) {
            this.openFaq = this.openFaq === id ? null : id
        },

        /** Défile d'une vignette, quelle que soit sa largeur réelle. */
        scrollGallery(direction) {
            const rail = this.$refs.rail
            if (!rail) return

            const card = rail.querySelector('figure')
            rail.scrollBy({ left: direction * ((card?.offsetWidth || 260) + 14), behavior: 'smooth' })
        },

        openLightbox(index) {
            this.lightboxIndex = index
            // Sans ce verrou, la page défile derrière la photo ouverte
            document.body.style.overflow = 'hidden'
        },

        closeLightbox() {
            this.lightboxIndex = null
            document.body.style.overflow = ''
        },

        prevPhoto() {
            this.lightboxIndex = (this.lightboxIndex - 1 + this.gallery.length) % this.gallery.length
        },

        nextPhoto() {
            this.lightboxIndex = (this.lightboxIndex + 1) % this.gallery.length
        },
    },
}
</script>

<template>
    <!-- ═══════════════════════ BANDEAU ═══════════════════════ -->
    <section class="hero">


        <div class="hero-inner shell">
            <p class="hero-location" v-if="hero.location">{{ hero.location }}</p>
            <p class="hero-tagline">{{ hero.tagline || 'Studio Privé Visagiste' }}</p>
            <p class="hero-sub">{{ hero.subtitle || 'Spécialisé dans les cheveux asiatiques' }}</p>

            <Link :href="bookingHref" class="btn btn--book btn--lg btn--charged">
                {{ hero.cta || 'Réserver' }}
            </Link>

            <div class="hero-socials" v-if="socials.length">
                <a
                    v-for="social in socials"
                    :key="social.key"
                    :href="settings[social.setting]"
                    :class="`hero-social--${social.key}`"
                    :aria-label="social.label"
                    target="_blank"
                    rel="noopener"
                >
                    <Icon :name="social.icon" :size="22" />
                </a>
            </div>
        </div>
    </section>

    <!-- Voile pleine largeur sur tout ce qui suit le bandeau. Posé ici plutôt
         que sur chaque section : appliqué à une colonne de 1100 px, il
         dessinerait un rectangle sombre aux bords visibles. -->
    <div class="page-veil">

    <!-- ═══════════════════════ PRESTATIONS ═══════════════════════ -->
    <section class="section shell" id="prestations" v-if="services.length">
        <h2 class="title-glow center" style="margin-bottom: 26px">{{ text.services_title }}</h2>

        <div class="service-list">
            <article v-for="service in services" :key="service.id" class="service-card reveal">
                <div class="service-visual">
                    <SmartImage :image="service.image" :alt="service.name" sizes="(min-width: 1024px) 340px, 90vw" />
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
                </p>

                <Link :href="serviceHref(service)" class="btn btn--book btn--block">Réserver</Link>
            </article>
        </div>

        <p class="center muted" style="margin-top: 22px; font-size: 13px">
            Après {{ lateOffer.hour }}h, le tarif est multiplié par {{ lateOffer.multiplier }}.
        </p>
    </section>

    <!-- ═══════════════════════ PRODUITS ═══════════════════════ -->
    <section class="section shell" v-if="products.length">
        <h2 class="title-glow center">{{ text.products_title }}</h2>
        <p class="shop-notice">{{ text.products_notice }}</p>

        <div class="shop-grid">
            <article v-for="product in products" :key="product.id" class="shop-item reveal">
                <div style="aspect-ratio: 1">
                    <SmartImage :image="product.image" :alt="product.name" sizes="(min-width: 768px) 260px, 45vw" />
                </div>
                <div style="padding: 10px 10px 14px">
                    <p class="shop-item-name" style="padding: 0 0 4px">{{ product.name }}</p>
                    <p class="muted" style="font-size: 12px; margin: 0 0 8px" v-if="product.description">
                        {{ product.description }}
                    </p>
                    <p class="service-price" style="font-size: 17px">{{ money(product.price) }}</p>
                </div>
            </article>
        </div>

        <p class="center" style="margin-top: 22px">
            <Link :href="route('store')" class="btn btn--ghost btn--sm">Voir toute la boutique</Link>
        </p>
    </section>

    <!-- ═══════════════════════ GALERIE ═══════════════════════ -->
    <section class="section shell" v-if="gallery.length">
        <h2 class="title-glow center" style="margin-bottom: 22px">{{ text.gallery_title }}</h2>

        <div class="carousel" ref="rail">
            <figure v-for="(item, i) in gallery" :key="item.id" @click="openLightbox(i)">
                <SmartImage :image="item.image" :alt="item.alt || 'Réalisation du studio'"
                            sizes="(min-width: 1024px) 300px, 70vw" />
            </figure>
        </div>

        <!-- Flèches de défilement, comme sur le wireframe -->
        <div class="carousel-nav">
            <button type="button" aria-label="Photos précédentes" @click="scrollGallery(-1)">
                <Icon name="left" :size="22" />
            </button>
            <Link :href="route('gallery')" class="carousel-count">Voir la galerie</Link>
            <button type="button" aria-label="Photos suivantes" @click="scrollGallery(1)">
                <Icon name="right" :size="22" />
            </button>
        </div>
    </section>

    <!-- ═══════════════════════ COMMENT ÇA MARCHE ═══════════════════════ -->
    <Reveal v-slot="{ revealed }" class="section shell">
        <!-- Titre et introduction ouvrent la cascade : rang 0, les cartes
             suivent à partir du rang 1. -->
        <h2
            class="title-glow center step-reveal"
            :class="{ 'is-in': revealed }"
            :style="{ '--i': 0, marginBottom: '8px' }"
        >{{ content.steps && content.steps.title || 'Comment ça marche' }}</h2>

        <p
            class="lede center step-reveal"
            :class="{ 'is-in': revealed }"
            :style="{ '--i': 0, marginBottom: '30px' }"
            v-if="content.steps && content.steps.subtitle"
        >{{ content.steps.subtitle }}</p>

        <div class="steps-layout">
            <div class="steps">
                <!-- Apparition en cascade : chaque carte hérite de son rang -->
                <article
                    v-for="(step, i) in steps"
                    :key="step.n"
                    class="step"
                    :class="{ 'is-in': revealed }"
                    :style="{ '--i': i + 1 }"
                >
                    <span class="step-number">Étape {{ String(step.n).padStart(2, '0') }}</span>

                    <span class="step-icon">
                        <Icon :name="step.icon" :size="26" />
                    </span>

                    <h3>{{ step.title }}</h3>
                    <p>{{ step.text }}</p>
                </article>
            </div>

            <!-- Prochain créneau réellement libre -->
            <aside class="card slot-card" v-if="nextSlot" :class="{ 'is-in': revealed }" :style="{ '--i': 4 }">
                <p class="slot-eyebrow">Prochaine disponibilité</p>
                <p class="slot-date">{{ formatDateLong(nextSlot.starts_at) }}</p>
                <p class="slot-time">{{ formatTime(nextSlot.starts_at) }}</p>
                <p class="slot-meta">{{ nextSlot.service_name }} · {{ nextSlot.duration_min }} min</p>

                <div class="slot-price">
                    <span>À partir de</span>
                    <strong>{{ money(nextSlot.price) }}</strong>
                </div>

                <Link :href="bookingHref" class="btn btn--book btn--block">Réserver</Link>

                <p class="slot-note">
                    Réponse sous {{ responseHours }} h · règlement au studio
                </p>
            </aside>
        </div>
    </Reveal>

    <!-- ═══════════════════════ FAQ ═══════════════════════ -->
    <section class="section shell" v-if="faqs.length">
        <h2 class="title-glow center" style="margin-bottom: 22px">{{ text.faq_title }}</h2>

        <div class="faq-list card" style="padding: 4px 18px 8px" ref="revealRoot">
            <div
                v-for="(faq, i) in faqs"
                :key="faq.id"
                class="faq-item"
                :class="{ 'is-open': openFaq === faq.id, 'is-in': revealed }"
                :style="{ '--i': i }"
            >
                <button type="button" class="faq-question" :aria-expanded="openFaq === faq.id"
                        @click="toggleFaq(faq.id)">
                    {{ faq.question }}
                    <span class="faq-toggle"><Icon name="plus" :size="18" /></span>
                </button>

                <!-- Enveloppe nécessaire : on anime la hauteur du conteneur,
                     le paragraphe garde la sienne. -->
                <div class="faq-answer-wrap">
                    <p class="faq-answer">{{ faq.answer }}</p>
                </div>
            </div>
        </div>

        <p class="center" style="margin-top: 22px">
            <Link :href="route('faq')" class="btn btn--ghost btn--sm">Toutes les questions</Link>
        </p>
    </section>

    </div><!-- /page-veil -->

    <!-- Photo en plein écran -->
    <div
        v-if="currentPhoto"
        class="lightbox"
        role="dialog"
        aria-modal="true"
        aria-label="Photo en plein écran"
        @click="closeLightbox"
    >
        <button type="button" class="lightbox-close" aria-label="Fermer" @click.stop="closeLightbox">
            <Icon name="close" :size="22" />
        </button>

        <button v-if="gallery.length > 1" type="button" class="lightbox-nav-btn"
                aria-label="Photo précédente" @click.stop="prevPhoto">
            <Icon name="left" :size="24" />
        </button>

        <figure class="lightbox-figure" @click.stop>
            <img :src="currentPhoto.image.url" :alt="currentPhoto.alt || 'Réalisation du studio'">
            <figcaption v-if="currentPhoto.alt">{{ currentPhoto.alt }}</figcaption>
        </figure>

        <button v-if="gallery.length > 1" type="button" class="lightbox-nav-btn"
                aria-label="Photo suivante" @click.stop="nextPhoto">
            <Icon name="right" :size="24" />
        </button>
    </div>
</template>
