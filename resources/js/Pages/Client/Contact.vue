<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'

/**
 * Contact. L'adresse exacte n'est pas publiée : le studio est privé, elle est
 * communiquée à la confirmation du rendez-vous (cf. FAQ).
 */
export default {
    layout: AppLayout,
    components: { Icon },

    props: {
        content:  { type: Object, default: () => ({}) },
        channels: { type: Array,  default: () => [] },
    },

    computed: {
        header() {
            return this.content.header || {}
        },
    },

    methods: {
        iconFor(type) {
            return { instagram: 'instagram', tiktok: 'tiktok', email: 'mail' }[type] || 'mail'
        },
        colorFor(type) {
            return {
                instagram: 'linear-gradient(135deg,#833AB4,#FD1D1D,#FCB045)',
                tiktok:    '#0B0B0B',
                email:     '#1D8CF8',
            }[type] || 'var(--surface-solid)'
        },
    },
}
</script>

<template>
    <div class="shell section">
        <h1 class="title-glow center" style="margin-bottom: 30px">{{ header.title || 'Contact' }}</h1>

        <div class="card contact-list" style="padding: 22px 18px" v-if="channels.length">
            <a
                v-for="channel in channels"
                :key="channel.type"
                :href="channel.url"
                class="contact-row"
                target="_blank"
                rel="noopener"
            >
                <span class="contact-icon" :style="{ background: colorFor(channel.type) }">
                    <Icon :name="iconFor(channel.type)" :size="24" />
                </span>
                <span class="contact-value">{{ channel.label }}</span>
            </a>
        </div>

        <p class="muted center" style="margin-top: 24px; font-size: 13px">
            {{ header.note || "L'adresse complète et les informations d'accès vous sont communiquées à la confirmation de votre rendez-vous." }}
        </p>
    </div>
</template>
