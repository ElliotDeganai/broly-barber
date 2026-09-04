<script>
/**
 * Image à chargement progressif.
 *
 * L'aperçu flou s'affiche immédiatement — quelques centaines d'octets — puis
 * l'image nette se fond par-dessus une fois chargée. Le `srcset` laisse le
 * navigateur choisir la résolution adaptée : un téléphone ne télécharge pas
 * une image de 1200 px pour l'afficher en 380.
 */
export default {
    props: {
        // Objet produit par ImagePayload : { url, srcset, lqip }
        image: { type: Object, default: null },
        alt:   { type: String, default: '' },
        // Indique au navigateur la largeur d'affichage prévue, pour qu'il
        // choisisse la bonne déclinaison AVANT la mise en page.
        sizes: { type: String, default: '100vw' },
        // La première image visible ne doit pas être différée
        eager: { type: Boolean, default: false },
    },

    data() {
        return { loaded: false }
    },

    computed: {
        src() {
            return this.image?.url || null
        },
    },

    mounted() {
        // Image déjà en cache : l'événement « load » ne se déclenchera jamais,
        // et sans ce contrôle elle resterait invisible pour toujours.
        const el = this.$refs.img

        if (el?.complete && el.naturalWidth > 0) {
            this.loaded = true
        }
    },

    methods: {
        // Même en cas d'erreur on retire l'aperçu : mieux vaut une image
        // cassée visible qu'un rectangle flou permanent.
        done() {
            this.loaded = true
        },
    },
}
</script>

<template>
    <span class="smart-img" :class="{ 'is-loaded': loaded }">
        <img
            v-if="image && image.lqip"
            class="smart-img-lqip"
            :src="image.lqip"
            alt=""
            aria-hidden="true"
        >

        <img
            v-if="src"
            ref="img"
            class="smart-img-full"
            :src="src"
            :srcset="image.srcset || undefined"
            :sizes="image.srcset ? sizes : undefined"
            :alt="alt"
            :loading="eager ? 'eager' : 'lazy'"
            :fetchpriority="eager ? 'high' : 'auto'"
            decoding="async"
            @load="done"
            @error="done"
        >
    </span>
</template>
