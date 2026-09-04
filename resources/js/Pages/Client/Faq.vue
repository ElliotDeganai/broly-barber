<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Components/Icon.vue'
import revealOnce from '@/revealOnce'

/** FAQ en accordéon : les réponses sont longues, tout déplier noierait la page. */
export default {
    layout: AppLayout,
    components: { Icon },
    mixins: [revealOnce],

    props: {
        content: { type: Object, default: () => ({}) },
        faqs:    { type: Array,  default: () => [] },
    },

    data() {
        return { openId: null }
    },

    computed: {
        header() {
            return this.content.header || {}
        },
    },

    methods: {
        toggle(id) {
            this.openId = this.openId === id ? null : id
        },
    },
}
</script>

<template>
    <div class="shell section">
        <h1 class="title-glow center" style="margin-bottom: 30px">{{ header.title || 'FAQ' }}</h1>

        <div class="faq-list card" style="padding: 4px 18px 8px" v-if="faqs.length" ref="revealRoot">
            <div
                v-for="(faq, i) in faqs"
                :key="faq.id"
                class="faq-item"
                :class="{ 'is-open': openId === faq.id, 'is-in': revealed }"
                :style="{ '--i': i }"
            >
                <button
                    type="button"
                    class="faq-question"
                    :aria-expanded="openId === faq.id"
                    @click="toggle(faq.id)"
                >
                    {{ faq.question }}
                    <span class="faq-toggle"><Icon name="plus" :size="18" /></span>
                </button>

                <div class="faq-answer-wrap">
                    <p class="faq-answer">{{ faq.answer }}</p>
                </div>
            </div>
        </div>

        <p v-else class="muted center">La foire aux questions sera bientôt disponible.</p>
    </div>
</template>
