<script>
import { Link, router } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'
import SiteFooter from '@/Components/SiteFooter.vue'
import FlashToast from '@/Components/FlashToast.vue'

/**
 * Enveloppe du site public.
 *
 * Charte : logo en haut à gauche, menu hamburger en haut à droite. La barre
 * est transparente sur le visuel puis se densifie au défilement. La barre
 * d'onglets basse reprend les quatre icônes des maquettes.
 */
export default {
    components: { Link, Icon, SiteFooter, FlashToast },

    props: {
        // true : le contenu passe SOUS la barre (accueil, pages à visuel plein écran)
        flush: { type: Boolean, default: false },
        // false : masque le pied de page. L'accueil s'arrête au bouton et aux
        // réseaux sociaux, conformément à la maquette.
        footer: { type: Boolean, default: true },
    },

    data() {
        return {
            menuOpen: false,
            scrolled: false,
            loading: false,
            progress: 0,
        }
    },

    computed: {
        /** Fond commun, partagé par le middleware Inertia. */
        background() {
            return this.$page.props.background || null
        },

        /**
         * Volutes et particules, positions fixes.
         *
         * Écrites une fois pour toutes : un tirage aléatoire les ferait sauter
         * d'un endroit à l'autre à chaque rendu.
         */
        particles() {
            const embers = [
                { left: '14%', top: '62%', size: 4, dur: '6s',   delay: '0s' },
                { left: '24%', top: '68%', size: 3, dur: '7.5s', delay: '1s' },
                { left: '33%', top: '64%', size: 3, dur: '6.5s', delay: '2.1s' },
                { left: '10%', top: '56%', size: 3, dur: '8s',   delay: '3.2s' },
                { left: '41%', top: '70%', size: 4, dur: '7s',   delay: '4s' },
                { left: '19%', top: '72%', size: 2, dur: '9s',   delay: '5.1s' },
                { left: '52%', top: '66%', size: 3, dur: '7.8s', delay: '2.7s' },
                { left: '29%', top: '58%', size: 2, dur: '8.5s', delay: '6s' },
            ]
            const stars = [
                { left: '22%', top: '14%', size: 3, dur: '2.6s', delay: '0s' },
                { left: '78%', top: '11%', size: 2, dur: '3.4s', delay: '.9s' },
                { left: '88%', top: '31%', size: 3, dur: '3s',   delay: '1.8s' },
                { left: '9%',  top: '24%', size: 2, dur: '3.8s', delay: '.5s' },
                { left: '63%', top: '18%', size: 2, dur: '2.9s', delay: '2.4s' },
                { left: '36%', top: '9%',  size: 3, dur: '3.6s', delay: '1.3s' },
                { left: '71%', top: '42%', size: 2, dur: '3.2s', delay: '3.1s' },
                { left: '15%', top: '38%', size: 2, dur: '4s',   delay: '2s' },
            ]

            return [
                ...embers.map((p, i) => ({ ...p, type: 'ember', id: 'e' + i })),
                ...stars.map((p, i) => ({ ...p, type: 'star', id: 's' + i })),
            ].map((p) => ({
                id: p.id,
                type: p.type,
                style: {
                    left: p.left, top: p.top,
                    width: p.size + 'px', height: p.size + 'px',
                    '--dur': p.dur, '--delay': p.delay,
                },
            }))
        },

        auth() {
            return this.$page.props.auth
        },
        user() {
            return this.$page.props.auth.user
        },
        settings() {
            return this.$page.props.settings || {}
        },
        flash() {
            return this.$page.props.flash || {}
        },
        /** Les sept entrées de la maquette du menu déroulant. */
        menuLinks() {
            return [
                { key: 'home',     label: 'Accueil',            href: route('home') },
                { key: 'services', label: 'Prestations',        href: route('services') },
                { key: 'store',    label: 'Only Sayajin Store', href: route('store') },
                { key: 'gallery',  label: 'Galerie',            href: route('gallery') },
                { key: 'about',    label: 'Qui suis-je ?',      href: route('about') },
                { key: 'faq',      label: 'FAQ',                href: route('faq') },
                { key: 'contact',  label: 'Contact',            href: route('contact') },
            ]
        },
        /**
         * Bloc de compte, sous les sept entrées de la maquette : accès à
         * l'espace client, au back office pour le barber, et déconnexion.
         */
        accountLinks() {
            if (!this.user) {
                return [{ key: 'login', label: 'Connexion', href: route('client.login') }]
            }

            const links = [{ key: 'account', label: 'Mon espace', href: route('account') }]

            if (this.auth.permissions.manage_content) {
                links.push({ key: 'admin', label: 'Administration', href: route('admin.dashboard') })
            }

            return links
        },

        /** Barre basse : prestations, réservation, accueil, compte. */
        tabLinks() {
            return [
                { key: 'services', icon: 'scissors', href: route('services'), label: 'Prestations' },
                { key: 'booking',  icon: 'calendar', href: this.bookingHref,  label: 'Réserver' },
                { key: 'home',     icon: 'home',     href: route('home'),     label: 'Accueil' },
                { key: 'account',  icon: 'login',    href: this.accountHref,  label: this.user ? 'Mon espace' : 'Connexion' },
            ]
        },
        /**
         * Un admin n'a pas la permission de réserver : l'envoyer vers la page de
         * connexion le renverrait aussitôt en arrière, donnant l'impression que
         * le bouton ne marche pas. On l'oriente vers son back office.
         */
        bookingHref() {
            if (!this.user) return route('client.login')
            if (this.auth.permissions.manage_content) return route('admin.appointments.index')

            return route('booking.services')
        },
        accountHref() {
            return this.user ? route('account') : route('client.login')
        },
    },

    mounted() {
        this.onScroll()
        window.addEventListener('scroll', this.onScroll, { passive: true })

        // Indicateur de chargement pendant les changements de page
        this._start  = router.on('start', () => this.startLoading())
        this._finish = router.on('finish', () => this.stopLoading())
    },

    beforeUnmount() {
        window.removeEventListener('scroll', this.onScroll)
        this._start?.()
        this._finish?.()
        clearInterval(this._timer)
    },

    watch: {
        // Toute navigation referme le menu
        '$page.url'() {
            this.menuOpen = false
        },
        menuOpen(open) {
            document.body.style.overflow = open ? 'hidden' : ''
        },
    },

    beforeUnmount() {
        // Le blocage est posé sur <body>, qui survit au changement de page.
        // Sans cette libération, naviguer vers un écran servi par un autre
        // layout — la connexion, par exemple — laisse la page figée : le
        // menu a disparu, mais son verrou reste.
        document.body.style.overflow = ''
    },

    methods: {
        logout() {
            router.post(route('logout'))
        },

        onScroll() {
            this.scrolled = window.scrollY > 8
        },

        /** Progression simulée : on ne connaît pas la durée réelle de la requête. */
        startLoading() {
            this.loading = true
            this.progress = 12

            clearInterval(this._timer)
            this._timer = setInterval(() => {
                this.progress = Math.min(this.progress + Math.random() * 12, 88)
            }, 180)
        },

        stopLoading() {
            clearInterval(this._timer)
            this.progress = 100
            setTimeout(() => { this.loading = false; this.progress = 0 }, 320)
        },

        isActive(href) {
            return this.$page.url === new URL(href, window.location.origin).pathname
        },
    },
}
</script>

<template>
    <div class="app" :class="{ 'app--inner': !flush }">
        <!-- Fond commun à toutes les pages publiques. Sur les pages sans
             visuel plein écran, il est davantage assombri pour que le texte
             reste lisible : voir .app--inner dans le CSS. -->
        <div class="page-bg" v-if="background" aria-hidden="true">
            <img
                :src="background.url"
                :srcset="background.srcset || undefined"
                sizes="100vw"
                alt=""
                loading="eager"
                fetchpriority="high"
                decoding="async"
            >

            <div class="smoke">
                <span v-for="n in 6" :key="n" class="smoke-wisp" :class="`smoke-wisp--${n}`"></span>
            </div>

            <div class="hero-particles">
                <span v-for="p in particles" :key="p.id" :class="p.type" :style="p.style"></span>
            </div>
        </div>

        <!-- Chargement d'une page -->
        <div
            v-if="loading"
            class="route-progress"
            :style="{ width: progress + '%' }"
            role="progressbar"
            aria-label="Chargement de la page"
        ></div>

        <header class="topbar" :class="{ 'is-scrolled': scrolled || menuOpen }">
            <div class="topbar-inner">
                <Link :href="route('home')" class="brand">
                    <img v-if="settings.logo" :src="settings.logo" alt="Broly Asian Barber">
                    <span v-else class="brand-fallback">
                        Broly <span style="color: var(--yellow)">Asian Barber</span>
                    </span>
                </Link>

                <button
                    type="button"
                    class="burger"
                    :class="{ 'is-open': menuOpen }"
                    :aria-expanded="menuOpen"
                    aria-label="Ouvrir le menu"
                    @click="menuOpen = !menuOpen"
                >
                    <span></span><span></span><span></span>
                </button>
            </div>
        </header>

        <div v-if="menuOpen" class="menu-backdrop" @click="menuOpen = false"></div>

        <Transition name="menu">
            <nav v-if="menuOpen" class="menu-panel">
                <Link v-for="link in menuLinks" :key="link.key" :href="link.href">{{ link.label }}</Link>

                <Link :href="bookingHref" class="btn btn--solid menu-cta">Réserver</Link>

                <!-- Compte : séparé des pages du site, c'est une autre nature de lien -->
                <div class="menu-account">
                    <Link
                        v-for="link in accountLinks"
                        :key="link.key"
                        :href="link.href"
                        class="menu-account-link"
                        :class="{ 'is-admin': link.key === 'admin' }"
                    >{{ link.label }}</Link>

                    <button v-if="user" type="button" class="menu-account-link" @click="logout">
                        Déconnexion
                    </button>
                </div>
            </nav>
        </Transition>

        <FlashToast />

        <main :class="flush ? 'page page--flush' : 'page'">
            <slot />
        </main>

        <SiteFooter v-if="footer" />

        <nav class="tabbar">
            <Link
                v-for="tab in tabLinks"
                :key="tab.key"
                :href="tab.href"
                :class="{ 'is-active': isActive(tab.href) }"
                :aria-label="tab.label"
            >
                <Icon :name="tab.icon" :size="20" />
            </Link>
        </nav>
    </div>
</template>
