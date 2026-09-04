<script>
import AppLayout from '@/Layouts/AppLayout.vue'

/** « Qui suis-je » : page libre, rédigée depuis le back office. */
export default {
    layout: AppLayout,

    props: {
        page: { type: Object, default: null },
    },
}
</script>

<template>
    <div class="shell section">
        <h1 class="title-glow center" style="margin-bottom: 26px">
            {{ page ? page.title : 'Qui suis-je ?' }}
        </h1>

        <div v-if="page" class="card" style="padding: 0; overflow: hidden">
            <div class="skeleton" style="aspect-ratio: 4 / 3" v-if="page.image">
                <img :src="page.image" :alt="page.title" loading="lazy" decoding="async"
                     style="width: 100%; height: 100%; object-fit: cover">
            </div>
            <div style="padding: 22px 18px" class="prose" v-html="page.body"></div>
        </div>

        <p v-else class="muted center">Cette page est en cours de rédaction.</p>
    </div>
</template>
