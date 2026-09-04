/**
 * Révélation à l'entrée dans le champ de vision, une seule fois.
 *
 * Utilise IntersectionObserver plutôt que `animation-timeline: view()` : sur
 * une timeline liée au défilement, les délais échelonnés sont ignorés, or
 * c'est précisément l'apparition l'une après l'autre qu'on cherche ici.
 * IntersectionObserver fonctionne en outre sur tous les navigateurs.
 */
export default {
    data() {
        return { revealed: false }
    },

    mounted() {
        // Mouvement réduit demandé, ou navigateur sans observateur : tout est
        // affiché d'emblée plutôt que de rester invisible.
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

        if (reduced || !('IntersectionObserver' in window) || !this.$refs.revealRoot) {
            this.revealed = true

            return
        }

        this._observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry.isIntersecting) return

                this.revealed = true
                // Une fois révélé, on cesse d'observer : les éléments ne
                // doivent pas disparaître si l'on remonte la page.
                this._observer.disconnect()
            },
            // Déclenche quand un cinquième du bloc est visible : assez tôt pour
            // que l'animation soit vue, assez tard pour ne pas se jouer hors champ.
            { threshold: 0.2 },
        )

        this._observer.observe(this.$refs.revealRoot)
    },

    beforeUnmount() {
        this._observer?.disconnect()
    },
}
