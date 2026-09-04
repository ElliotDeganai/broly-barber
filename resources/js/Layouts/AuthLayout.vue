<script>
import { Link } from '@inertiajs/vue3'

/**
 * Enveloppe des écrans d'authentification.
 *
 * Volontairement dépouillée : ni menu, ni barre d'onglets. Sur un écran de
 * connexion, tout ce qui détourne de la saisie est du bruit. Seul le logo
 * ramène à l'accueil.
 */
export default {
    components: { Link },

    props: {
        title:    { type: String, default: null },
        subtitle: { type: String, default: null },
    },

    computed: {
        settings() {
            return this.$page.props.settings || {}
        },
        /** Le visuel du bandeau, partagé par les réglages du site. */
        background() {
            return this.settings.home_hero_image || null
        },
    },
}
</script>

<template>
    <div class="auth-shell" :class="{ 'has-media': background }">
        <!-- Visuel de l'accueil, fortement assombri : il pose l'ambiance sans
             jamais concurrencer la lisibilité des champs. -->
        <div class="auth-media" v-if="background" aria-hidden="true">
            <img :src="background" alt="">
        </div>

        <!-- Aura verte : seul décor quand aucun visuel n'est renseigné -->
        <div class="auth-aura" aria-hidden="true"></div>

        <div class="auth-box">
            <Link :href="route('home')" class="auth-brand">
                <img v-if="settings.logo" :src="settings.logo" alt="Broly Asian Barber">
                <span v-else class="brand-fallback">
                    Broly <span style="color: var(--yellow)">Asian Barber</span>
                </span>
            </Link>

            <div class="card auth-card">
                <h1 class="title-glow auth-heading" v-if="title">{{ title }}</h1>
                <p class="auth-lede" v-if="subtitle">{{ subtitle }}</p>

                <slot />
            </div>

            <p class="auth-back">
                <Link :href="route('home')">← Retour au site</Link>
            </p>
        </div>
    </div>
</template>
