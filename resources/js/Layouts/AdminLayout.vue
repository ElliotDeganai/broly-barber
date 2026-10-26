<script>
import { Link, router } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'
import FlashToast from '@/Components/FlashToast.vue'

/**
 * Enveloppe du back office.
 *
 * Barre du haut sur toute la largeur — logo à gauche, menu du compte à droite.
 * Barre latérale en dessous, liens groupés par domaine : onze entrées à plat
 * forcent à lire toute la liste, quatre familles rendent le repérage immédiat.
 */
export default {
    components: { Link, Icon, FlashToast },

    data() {
        return {
            sidebarOpen: false,
            menuOpen: false,
        }
    },

    computed: {
        user() {
            return this.$page.props.auth.user
        },
        settings() {
            return this.$page.props.settings || {}
        },
        initial() {
            return (this.user?.name || '?').charAt(0).toUpperCase()
        },

        groups() {
            return [
                {
                    key: 'pilotage',
                    label: 'Pilotage',
                    links: [
                        { label: 'Tableau de bord', route: 'admin.dashboard', icon: 'home' },
                        { label: 'Statistiques',    route: 'admin.stats',     icon: 'alert' },
                    ],
                },
                {
                    key: 'activite',
                    label: 'Activité',
                    links: [
                        // Aucun paramètre : la page s'ouvre sur son état par
                        // défaut — planning du jour, confirmés et en attente,
                        // filtres repliés. Forcer un filtre depuis le menu le
                        // ferait passer pour un réglage posé par l'utilisateur.
                        { label: 'Rendez-vous',    route: 'admin.appointments.index', icon: 'calendar' },
                        { label: 'Disponibilités', route: 'admin.availability.index', icon: 'clock' },
                        { label: 'Clients',        route: 'admin.clients.index',      icon: 'user' },
                    ],
                },
                {
                    key: 'catalogue',
                    label: 'Catalogue',
                    links: [
                        { label: 'Prestations', route: 'admin.services.index', icon: 'scissors' },
                        { label: 'Boutique',    route: 'admin.products.index', icon: 'bag' },
                        { label: 'Ventes',      route: 'admin.sales.index',    icon: 'check' },
                    ],
                },
                {
                    key: 'site',
                    label: 'Site public',
                    links: [
                        { label: 'Galerie', route: 'admin.gallery.index', icon: 'photo' },
                        { label: 'FAQ',     route: 'admin.faqs.index',    icon: 'help' },
                        { label: 'Contenu', route: 'admin.content.index', icon: 'pencil' },
                    ],
                },
                {
                    key: 'reglages',
                    label: 'Réglages',
                    links: [
                        { label: 'Administrateurs', route: 'admin.admins.index', icon: 'settings' },
                    ],
                },
            ]
        },
    },

    watch: {
        // Toute navigation referme les deux panneaux
        '$page.url'() {
            this.sidebarOpen = false
            this.menuOpen = false
        },
        sidebarOpen(open) {
            document.body.style.overflow = open ? 'hidden' : ''
        },
    },

    mounted() {
        this._outside = (e) => {
            if (this.menuOpen && !this.$refs.account?.contains(e.target)) {
                this.menuOpen = false
            }
        }
        this._escape = (e) => {
            if (e.key === 'Escape') { this.menuOpen = false; this.sidebarOpen = false }
        }

        document.addEventListener('click', this._outside)
        document.addEventListener('keydown', this._escape)
    },

    beforeUnmount() {
        document.removeEventListener('click', this._outside)
        document.removeEventListener('keydown', this._escape)
        document.body.style.overflow = ''
    },

    methods: {
        isActive(name) {
            // startsWith sur le CHEMIN seul : le formulaire d'une prestation
            // garde « Prestations » actif, et les paramètres de filtre — ?status=…
            // — n'empêchent pas la mise en évidence.
            const path = new URL(route(name), window.location.origin).pathname
            const here = this.$page.url.split('?')[0].replace(/\/$/, '')

            // « /admin » est le préfixe de toutes les autres pages : sans
            // égalité stricte, le tableau de bord resterait allumé partout.
            return path === '/admin' ? here === '/admin' : here.startsWith(path)
        },
        logout() {
            // Destination explicite, comme sur le site public : on ne dépend
            // pas de la redirection du contrôleur de Breeze.
            router.post(route('logout'), {}, {
                onFinish: () => router.visit(route('home'), { replace: true }),
            })
        },
    },
}
</script>

<template>
    <div class="admin">
        <FlashToast />

        <header class="admin-topbar">
            <button type="button" class="burger admin-burger" :class="{ 'is-open': sidebarOpen }"
                    aria-label="Menu" @click="sidebarOpen = !sidebarOpen">
                <span></span><span></span><span></span>
            </button>

            <Link :href="route('admin.dashboard')" class="admin-logo">
                <img v-if="settings.logo" :src="settings.logo" alt="Broly Asian Barber">
                <span v-else class="brand-fallback">
                    Broly <span style="color: var(--yellow)">Asian Barber</span>
                </span>
            </Link>

            <!-- Menu du compte : ce qui concerne la session, pas la gestion -->
            <div class="admin-account" ref="account">
                <button type="button" class="admin-account-btn" :aria-expanded="menuOpen"
                        aria-label="Menu du compte" @click.stop="menuOpen = !menuOpen">
                    <span class="admin-avatar">{{ initial }}</span>
                    <span class="admin-account-name">{{ user?.name }}</span>
                    <Icon name="right" :size="15" class="admin-account-caret" />
                </button>

                <Transition name="menu">
                    <div v-if="menuOpen" class="admin-dropdown">
                        <Link :href="route('profile.edit')" class="admin-dropdown-link">
                            <Icon name="user" :size="16" />
                            <span>Mon profil</span>
                        </Link>

                        <Link :href="route('home')" class="admin-dropdown-link">
                            <Icon name="login" :size="16" />
                            <span>Voir le site</span>
                        </Link>

                        <button type="button" class="admin-dropdown-link admin-dropdown-link--danger"
                                @click="logout">
                            <Icon name="logout" :size="16" />
                            <span>Déconnexion</span>
                        </button>
                    </div>
                </Transition>
            </div>
        </header>

        <div v-if="sidebarOpen" class="admin-backdrop" @click="sidebarOpen = false"></div>

        <aside class="admin-sidebar" :class="{ 'is-open': sidebarOpen }">
            <nav class="admin-menu">
                <div class="admin-group" v-for="group in groups" :key="group.key">
                    <p class="admin-group-label">{{ group.label }}</p>

                    <Link
                        v-for="link in group.links"
                        :key="link.route"
                        :href="route(link.route, link.params || {})"
                        class="admin-link"
                        :class="{ 'is-active': isActive(link.route) }"
                    >
                        <Icon :name="link.icon" :size="17" />
                        <span>{{ link.label }}</span>
                    </Link>
                </div>
            </nav>
        </aside>

        <main class="admin-page">
            <div class="admin-content">
                <slot />
            </div>
        </main>
    </div>
</template>
