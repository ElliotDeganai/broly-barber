<script>
import { Link } from '@inertiajs/vue3'
import Icon from '@/Components/Icon.vue'

export default {
    components: { Link, Icon },

    computed: {
        settings() {
            return this.$page.props.settings || {}
        },
        year() {
            return new Date().getFullYear()
        },
        socials() {
            return [
                { key: 'instagram_url', icon: 'instagram', label: 'Instagram' },
                { key: 'tiktok_url',    icon: 'tiktok',    label: 'TikTok' },
                { key: 'contact_email', icon: 'mail',      label: 'Email', mailto: true },
            ].filter((s) => this.settings[s.key])
        },
        legalLinks() {
            return [
                { slug: 'mentions-legales', label: 'Mentions légales' },
                { slug: 'confidentialite',  label: 'Confidentialité' },
                { slug: 'cookies',          label: 'Cookies' },
                { slug: 'annulation',       label: "Conditions d'annulation" },
            ]
        },
    },
}
</script>

<template>
    <footer class="site-footer">
        <div class="shell">
            <div class="footer-socials" v-if="socials.length">
                <a
                    v-for="social in socials"
                    :key="social.key"
                    :href="social.mailto ? 'mailto:' + settings[social.key] : settings[social.key]"
                    :aria-label="social.label"
                    target="_blank"
                    rel="noopener"
                >
                    <Icon :name="social.icon" :size="19" />
                </a>
            </div>

            <div class="footer-links">
                <Link :href="route('faq')">FAQ</Link>
                <Link :href="route('contact')">Contact</Link>
                <Link v-for="page in legalLinks" :key="page.slug" :href="route('page', page.slug)">
                    {{ page.label }}
                </Link>
            </div>

            <p class="footer-legal">
                © {{ year }} {{ settings.salon_name || 'Broly Asian Barber' }} —
                aucun paiement en ligne, le règlement s'effectue au studio.
            </p>
        </div>
    </footer>
</template>
