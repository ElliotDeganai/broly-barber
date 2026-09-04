<script>
import AppLayout from '@/Layouts/AppLayout.vue'

/** Pages légales : mentions, confidentialité, cookies, annulation. */
export default {
    layout: AppLayout,

    props: {
        page: { type: Object, required: true },
    },
}
</script>

<template>
    <div class="shell section">
        <h1 class="title-glow center" style="margin-bottom: 26px">{{ page.title }}</h1>

        <!-- v-html : le contenu vient de l'éditeur du back office et a été
             filtré côté serveur par HtmlSanitizer. -->
        <div class="card prose" style="padding: 24px 20px" v-html="page.body"></div>
    </div>
</template>
