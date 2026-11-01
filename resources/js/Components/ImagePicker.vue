<script>
/**
 * Choix d'un visuel, avec recadrage avant envoi.
 *
 * Le cadrage se fait ici, dans le navigateur : le fichier qui part vers le
 * serveur est déjà aux bonnes proportions. Le reste de la chaîne — variantes,
 * aperçu flou, stockage — n'a donc rien à savoir du recadrage, et une photo
 * prise au téléphone en 4000 px n'est plus téléversée en entier pour être
 * réduite ensuite.
 *
 * Le cadre est verrouillé sur les proportions d'affichage réelles du visuel.
 * C'est tout l'objet de l'écran : une photo cadrée « à peu près » se fait
 * rogner par le CSS, et c'est toujours le haut du crâne ou le menton qui
 * disparaît.
 */
import Cropper from 'cropperjs'
import 'cropperjs/dist/cropper.css'
import Icon from '@/Components/Icon.vue'

export default {
    components: { Icon },

    props: {
        // Fichier retenu — déjà recadré. null tant que rien n'est choisi.
        modelValue: { type: File, default: null },
        // Proportions imposées, en largeur / hauteur. 0 laisse le cadre libre.
        ratio:      { type: Number, default: 0 },
        // Visuel déjà enregistré, montré tant qu'on n'en choisit pas un autre
        current:    { type: String, default: null },
        alt:        { type: String, default: '' },
        error:      { type: String, default: null },
        /*
         * Plafond du côté le plus long, en pixels. Au-delà, le serveur
         * redimensionne de toute façon : exporter plus gros ne ferait
         * qu'allonger le téléversement.
         */
        maxSide:    { type: Number, default: 1600 },
        /*
         * Largeur en deçà de laquelle le visuel sera flou à l'affichage.
         * C'est la plus grande variante produite par le serveur : sous cette
         * valeur, il n'a plus qu'à agrandir. Le piège est propre au tactile —
         * la pince permet de zoomer bien au-delà de ce que la photo contient.
         */
        minUtile:   { type: Number, default: 1200 },
    },

    emits: ['update:modelValue'],

    data() {
        return {
            // URL d'objet de la source en cours de recadrage
            source: null,
            // URL d'objet du résultat, pour l'aperçu
            apercu: null,
            nomOrigine: null,
            typeOrigine: null,
            ouvert: false,
            occupe: false,
            // Dimensions de la zone retenue, en pixels de l'image d'origine
            zone: null,
        }
    },

    computed: {
        visuel() {
            return this.apercu || this.current
        },

        /** Style de l'aperçu : il montre le cadrage tel qu'il sera sur le site. */
        cadreStyle() {
            return this.ratio ? { aspectRatio: String(this.ratio) } : {}
        },

        /** Dimensions de sortie, après plafonnement du côté le plus long. */
        sortie() {
            if (!this.zone) return null

            const f = Math.min(1, this.maxSide / Math.max(this.zone.width, this.zone.height))

            return {
                width: Math.round(this.zone.width * f),
                height: Math.round(this.zone.height * f),
            }
        },

        /** Zone trop petite : le serveur devra agrandir, le visuel sera mou. */
        tropPetit() {
            return Boolean(this.sortie) && this.sortie.width < this.minUtile
        },
    },

    mounted() {
        // Écoute posée une fois pour toutes : la fenêtre n'existe dans le DOM
        // que pendant le recadrage, un écouteur porté par elle manquerait les
        // frappes reçues pendant son montage.
        this.auClavier = (e) => {
            if (e.key === 'Escape' && this.ouvert) this.fermer()
        }

        window.addEventListener('keydown', this.auClavier)
    },

    beforeUnmount() {
        window.removeEventListener('keydown', this.auClavier)
        this.detruire()
        this.libererSource()

        // Une URL d'objet retient son fichier en mémoire tant qu'on ne la
        // révoque pas, et le formulaire peut en créer une par essai.
        if (this.apercu) URL.revokeObjectURL(this.apercu)
    },

    methods: {
        /** Le champ réel reste masqué : son rendu natif ne suit pas la charte. */
        parcourir() {
            this.$refs.input.click()
        },

        choisir(event) {
            const fichier = event.target.files[0]

            // Le champ est remis à zéro tout de suite : sans cela, rouvrir le
            // sélecteur et reprendre le même fichier n'émettrait plus
            // d'événement « change », et l'écran resterait fermé.
            event.target.value = ''

            if (!fichier) return

            this.nomOrigine  = fichier.name
            this.typeOrigine = fichier.type

            this.libererSource()
            this.source = URL.createObjectURL(fichier)
            this.ouvert = true

            // Le <img> n'existe qu'une fois la fenêtre rendue
            this.$nextTick(() => this.demarrer())
        },

        demarrer() {
            const img = this.$refs.image

            if (!img) return

            this.detruire()

            this.cropper = new Cropper(img, {
                aspectRatio: this.ratio || NaN,
                // 1 : le cadre ne peut pas sortir de l'image. Sans cette
                // contrainte on peut cadrer sur du vide, et le résultat
                // arrive avec des bandes transparentes.
                viewMode: 1,
                autoCropArea: 1,
                background: false,
                movable: true,
                zoomable: true,
                responsive: true,
                checkOrientation: true,
                // Émis à chaque déplacement, redimensionnement ou zoom
                crop: (e) => {
                    this.zone = { width: e.detail.width, height: e.detail.height }
                },
            })
        },

        detruire() {
            this.cropper?.destroy()
            this.cropper = null
        },

        libererSource() {
            if (this.source) URL.revokeObjectURL(this.source)

            this.source = null
        },

        pivoter(degres) {
            this.cropper?.rotate(degres)
        },

        reinitialiser() {
            this.cropper?.reset()
        },

        fermer() {
            this.detruire()
            this.libererSource()
            this.ouvert = false
        },

        /**
         * Type de sortie.
         *
         * PNG et WebP sont conservés : un PNG détouré réexporté en JPEG
         * reviendrait avec un fond noir. Tout le reste part en JPEG, seul
         * format dont la compression tient la photo sans gonfler le fichier.
         */
        typeSortie() {
            return ['image/png', 'image/webp'].includes(this.typeOrigine)
                ? this.typeOrigine
                : 'image/jpeg'
        },

        nomSortie(type) {
            const base = (this.nomOrigine || 'visuel').replace(/\.[^.]+$/, '')
            const ext  = { 'image/png': 'png', 'image/webp': 'webp' }[type] || 'jpg'

            return `${base}.${ext}`
        },

        valider() {
            if (!this.cropper || this.occupe) return

            this.occupe = true

            /*
             * Dimensions calculées ici, et passées explicitement.
             *
             * Les options maxWidth / maxHeight du recadreur ne plafonnent pas
             * la zone retenue : elles réduisent l'image ENTIÈRE à cette
             * largeur, puis découpent dedans. Une photo de 4032 px plafonnée
             * à 1600 ressortait ainsi en 900 px de large — sous la plus grande
             * variante produite par le serveur, qui n'avait plus qu'à
             * agrandir. On fixe donc width et height, que le recadreur
             * respecte au pixel près.
             */
            // Les mêmes dimensions que celles annoncées dans la fenêtre : un
            // second calcul ici afficherait un chiffre et en enregistrerait
            // un autre, à un pixel près.
            const zone = this.cropper.getData()
            const f = Math.min(1, this.maxSide / Math.max(zone.width, zone.height))
            const taille = this.sortie || {
                width: Math.round(zone.width * f),
                height: Math.round(zone.height * f),
            }

            const canvas = this.cropper.getCroppedCanvas({
                ...taille,
                imageSmoothingQuality: 'high',
            })

            if (!canvas) {
                this.occupe = false

                return
            }

            const type = this.typeSortie()

            canvas.toBlob((blob) => {
                this.occupe = false

                if (!blob) return

                const fichier = new File([blob], this.nomSortie(type), {
                    type,
                    lastModified: Date.now(),
                })

                if (this.apercu) URL.revokeObjectURL(this.apercu)

                this.apercu = URL.createObjectURL(fichier)
                this.$emit('update:modelValue', fichier)
                this.fermer()
            }, type, 0.9)
        },

        /** Reprendre le cadrage du fichier déjà retenu, sans le rechoisir. */
        recadrer() {
            if (!this.modelValue) return

            this.nomOrigine  = this.modelValue.name
            this.typeOrigine = this.modelValue.type

            this.libererSource()
            this.source = URL.createObjectURL(this.modelValue)
            this.ouvert = true
            this.$nextTick(() => this.demarrer())
        },

        retirer() {
            if (this.apercu) URL.revokeObjectURL(this.apercu)

            this.apercu = null
            this.$emit('update:modelValue', null)
        },
    },
}
</script>

<template>
    <div class="picker">
        <div class="picker-cadre" :style="cadreStyle">
            <img v-if="visuel" :src="visuel" :alt="alt">
            <span v-else class="picker-vide">
                <Icon name="photo" :size="26" />
                <em>Aucun visuel</em>
            </span>
        </div>

        <div class="picker-actions">
            <button type="button" class="btn btn--ghost btn--sm" @click="parcourir">
                {{ visuel ? 'Changer' : 'Choisir une image' }}
            </button>
            <button v-if="modelValue" type="button" class="btn btn--ghost btn--sm" @click="recadrer">
                Recadrer
            </button>
            <button v-if="modelValue" type="button" class="btn btn--ghost btn--sm btn--danger" @click="retirer">
                Retirer
            </button>
        </div>

        <input ref="input" type="file" accept="image/*" class="picker-input" @change="choisir">

        <span class="field-error" v-if="error">{{ error }}</span>

        <!-- Fenêtre de recadrage. Montée seulement à l'ouverture : Cropper
             mesure son conteneur au démarrage et renverrait des dimensions
             nulles sur un élément caché. -->
        <!-- .self : un relâchement de souris qui part du cadre de recadrage et
             finit sur le voile ne doit pas fermer la fenêtre, sinon un
             ajustement un peu large efface le travail en cours. -->
        <div v-if="ouvert" class="crop-fond" role="dialog" aria-modal="true"
             aria-label="Recadrer l'image" @click.self="fermer">
            <div class="crop-fenetre">
                <p class="crop-titre">Recadrer</p>
                <p class="field-help" style="margin-bottom: 12px">
                    Déplacez l'image et ajustez le cadre. La zone retenue est exactement
                    ce qui sera affiché sur le site.
                </p>

                <div class="crop-scene">
                    <img ref="image" :src="source" alt="">
                </div>

                <!-- Taille réelle de ce qui sera enregistré. Au doigt, la pince
                     laisse zoomer bien au-delà de ce que la photo contient :
                     sans ce repère, on ne découvre le flou qu'une fois le
                     visuel en ligne. -->
                <p class="crop-taille" :class="{ 'is-faible': tropPetit }" v-if="sortie">
                    <Icon v-if="tropPetit" name="alert" :size="15" />
                    <span>
                        Enregistré en {{ sortie.width }} × {{ sortie.height }} px.
                        <template v-if="tropPetit">
                            En dessous de {{ minUtile }} px de large, le visuel sera
                            flou à l'affichage : élargissez le cadre.
                        </template>
                    </span>
                </p>

                <div class="crop-outils">
                    <button type="button" class="btn-icon" aria-label="Pivoter à gauche" @click="pivoter(-90)">
                        <Icon name="rotateLeft" :size="18" />
                    </button>
                    <button type="button" class="btn-icon" aria-label="Pivoter à droite" @click="pivoter(90)">
                        <Icon name="rotateRight" :size="18" />
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" @click="reinitialiser">Tout remettre</button>
                </div>

                <div class="row-actions">
                    <button type="button" class="btn btn--solid btn--sm" :disabled="occupe" @click="valider">
                        {{ occupe ? 'Découpe…' : 'Valider le cadrage' }}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" @click="fermer">Annuler</button>
                </div>
            </div>
        </div>
    </div>
</template>
