<script>
/**
 * Avant / après commandé par le défilement.
 *
 * La séparation suit la position du bloc dans la fenêtre : aucune minuterie,
 * rien ne tourne quand personne ne regarde, et remonter d'un cran fait
 * revenir l'avant. C'est aussi le seul geste identique au doigt et à la souris.
 *
 * Les deux boutons reprennent la main : dès qu'on en touche un, le défilement
 * cesse de piloter — sans quoi les deux commandes se disputeraient la valeur.
 */

/*
 * Un seul écouteur de défilement pour toutes les cartes de la page, et une
 * mise à jour par image : six prestations ne doivent pas faire six écouteurs
 * qui lisent chacun la position de leur élément à chaque pixel parcouru.
 */
const abonnes = new Set()
let planifie = false
let branche = false

function parcourir() {
    planifie = false
    abonnes.forEach((maj) => maj())
}

function demander() {
    if (planifie) return

    planifie = true
    requestAnimationFrame(parcourir)
}

function brancher(maj) {
    abonnes.add(maj)

    if (!branche) {
        window.addEventListener('scroll', demander, { passive: true })
        window.addEventListener('resize', demander)
        branche = true
    }

    demander()
}

function debrancher(maj) {
    abonnes.delete(maj)
}

export default {
    props: {
        before: { type: String, required: true },
        after:  { type: String, required: true },
        alt:    { type: String, default: '' },
    },

    data() {
        return {
            progression: 0,
            // Vaut « avant » ou « apres » une fois qu'un bouton a été utilisé
            choix: null,
            mouvementReduit: false,
            // Révélation d'entrée, pour une carte déjà à l'écran au chargement
            intro: false,
        }
    },

    computed: {
        /** Part de l'image « après » déjà dévoilée par le défilement, de 0 à 1. */
        part() {
            if (this.mouvementReduit) return 1

            return this.progression
        },

        /**
         * Découpe de l'image « avant ».
         *
         * Dès qu'un bouton a été utilisé, la découpe se referme : le changement
         * passe alors par l'opacité, pas par la séparation. Les deux
         * s'animent ensemble, donc l'ouverture de la découpe est masquée par
         * le fondu et on ne voit qu'un fondu.
         */
        decoupe() {
            return this.choix ? 0 : this.part
        },

        opacite() {
            if (this.mouvementReduit) return this.choix === 'avant' ? 1 : 0

            return this.choix === 'apres' ? 0 : 1
        },

        etat() {
            if (this.choix) return this.choix

            return this.part > 0.5 ? 'apres' : 'avant'
        },

    },

    mounted() {
        this.mouvementReduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches

        if (this.mouvementReduit) return

        this.maj = () => this.mesurer()
        brancher(this.maj)
        this.entrer()
    },

    beforeUnmount() {
        if (this.maj) debrancher(this.maj)

        clearTimeout(this.depart)
        clearTimeout(this.fin)
    },

    methods: {
        /**
         * Progression du bloc dans la fenêtre.
         *
         * Zéro tant qu'il arrive par le bas, un lorsqu'il a franchi les deux
         * tiers de la hauteur visible : la révélation se joue donc pendant la
         * remontée du bloc, là où le regard est déjà dessus.
         */
        /**
         * Une carte déjà visible au chargement serait affichée d'emblée en
         * « après » : sa position la place au-delà du seuil. On rejoue alors
         * la révélation une fois, puis le défilement reprend la main.
         */
        entrer() {
            this.mesurer()

            const cible = this.progression

            if (cible < 0.05) return

            this.progression = 0
            this.intro = true

            this.depart = setTimeout(() => { this.progression = cible }, 60)
            this.fin    = setTimeout(() => { this.intro = false }, 1300)
        },

        mesurer() {
            if (this.choix || this.intro || !this.$refs.cadre) return

            const r = this.$refs.cadre.getBoundingClientRect()
            const h = window.innerHeight || 1
            const p = 1 - (r.top - h * 0.2) / (h * 0.55)

            this.progression = Math.min(1, Math.max(0, p))
        },

        choisir(vue) {
            this.choix = vue
        },
    },
}
</script>

<template>
    <div class="ba-wrap">
        <div class="service-visual ba" :class="{ 'is-fige': choix, 'is-intro': intro }" ref="cadre"
             :style="{ '--p': decoupe, '--o': opacite }">
            <img class="ba-apres" :src="after" :alt="alt" loading="lazy" decoding="async">
            <!-- L'avant double l'après pour les lecteurs d'écran : une seule
                 description suffit, c'est la même prestation. -->
            <img class="ba-avant" :src="before" alt="" aria-hidden="true" loading="lazy" decoding="async">

            <!-- Plus de séparation une fois en fondu : il n'y a plus rien à séparer -->
            <span class="ba-ligne" v-if="!mouvementReduit && !choix"></span>
            <span class="ba-tag">{{ etat === 'apres' ? 'Après' : 'Avant' }}</span>
        </div>

        <div class="ba-onglets">
            <button type="button" :class="{ 'is-active': etat === 'avant' }"
                    :aria-pressed="etat === 'avant'" @click="choisir('avant')">Avant</button>
            <button type="button" :class="{ 'is-active': etat === 'apres' }"
                    :aria-pressed="etat === 'apres'" @click="choisir('apres')">Après</button>
        </div>
    </div>
</template>
