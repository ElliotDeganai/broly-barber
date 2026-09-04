<script>
/**
 * Révèle son contenu à l'entrée dans le champ de vision, une seule fois.
 *
 * Composant plutôt que mixin : une page peut contenir plusieurs zones à
 * révéler indépendamment, or un mixin n'offre qu'une seule référence par
 * composant — les étapes et la FAQ entreraient en conflit.
 */
export default {
    data() {
        return { revealed: false }
    },

    mounted() {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

        // Mouvement réduit ou navigateur sans observateur : tout est affiché
        // d'emblée plutôt que de rester invisible.
        if (reduced || !('IntersectionObserver' in window)) {
            this.revealed = true

            return
        }

        this._observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry.isIntersecting) return

                this.revealed = true
                // Le contenu ne doit pas disparaître si l'on remonte la page
                this._observer.disconnect()
            },
            /**
             * Seuil à zéro plutôt qu'un pourcentage de l'élément.
             *
             * Un seuil de 20 % ne se déclenche jamais quand l'élément est plus
             * de cinq fois plus haut que la fenêtre — le cas des trois étapes
             * empilées sur mobile. Le ratio plafonne alors sous le seuil et le
             * contenu reste invisible pour toujours.
             *
             * Avec un seuil nul et une marge négative en bas, on déclenche
             * quand le haut de l'élément a franchi 85 % de la hauteur d'écran :
             * le comportement ne dépend plus de la taille du bloc.
             */
            { threshold: 0, rootMargin: '0px 0px -15% 0px' },
        )

        this._observer.observe(this.$el)
    },

    beforeUnmount() {
        this._observer?.disconnect()
    },
}
</script>

<template>
    <div><slot :revealed="revealed" /></div>
</template>
