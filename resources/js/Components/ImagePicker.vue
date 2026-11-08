<script>
/**
 * Choix d'un visuel, avec recadrage et aperçu par appareil avant envoi.
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
 *
 * Le zoom est borné. Pas averti : borné. Resserrer le cadre jette des pixels
 * que rien ne rend ensuite, et le flou n'apparaît qu'une fois le visuel en
 * ligne, sur l'écran de quelqu'un d'autre. Tant que la photo d'origine le
 * permet, le recadreur refuse donc d'aller sous la définition dont le plus
 * exigeant des appareils a besoin.
 */
import Cropper from 'cropperjs'
import 'cropperjs/dist/cropper.css'
import Icon from '@/Components/Icon.vue'

/*
 * Largeurs d'affichage réelles du visuel d'une prestation, mesurées dans un
 * navigateur sur la feuille de style du site, et non calculées de tête.
 *
 * Le classement surprend : le téléphone est l'appareil le plus exigeant,
 * loin devant le grand écran. La carte d'une prestation mesure environ
 * 370 px de large partout — une seule colonne sur téléphone, trois sur
 * ordinateur — mais un téléphone récent affiche 3 pixels physiques par
 * pixel CSS, là où un écran de bureau en affiche un. D'où 1218 px à fournir
 * au téléphone contre 377 au PC.
 *
 * C'est la raison pour laquelle un cadrage « qui rend bien » sur l'écran du
 * barbier peut être franchement mou sur le téléphone de son client.
 */
const CIBLES_PRESTATION = [
    { nom: 'Téléphone',  css: 406, dpr: 3 },
    { nom: 'Tablette',   css: 372, dpr: 2 },
    { nom: 'Ordinateur', css: 377, dpr: 1 },
]

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
         * Où ce visuel sera affiché, et à quelle taille. `css` est la largeur
         * du cadre en pixels CSS, `dpr` la densité de l'écran. Le produit des
         * deux donne les pixels réels à fournir.
         *
         * À remplacer par les mesures de la surface concernée lorsque le
         * composant sert ailleurs : un bandeau en pleine largeur n'a pas les
         * mêmes exigences qu'une vignette.
         */
        cibles: { type: Array, default: () => CIBLES_PRESTATION },
        /*
         * Largeur de la plus grande variante produite par le serveur.
         *
         * C'est le vrai plafond de la chaîne : quoi qu'on exporte, le site ne
         * servira jamais plus large que ça, et exporter au-delà ne ferait
         * qu'allonger le téléversement pour rien.
         *
         * À garder aligné sur la plus grande valeur de VARIANTS dans
         * ImageUploadService. Les deux doivent bouger ensemble : un plafond
         * plus haut ici annoncerait une finesse que le serveur ne livre pas.
         */
        plafondServeur: { type: Number, default: 1200 },
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
            // Le geste en cours a-t-il commencé sur le voile ?
            debutFond: false,
            // Dimensions de la zone retenue, en pixels de l'image d'origine
            zone: null,
            /*
             * Largeur, en pixels d'origine, de la plus grande zone que cette
             * photo peut offrir aux proportions imposées. Elle détermine
             * jusqu'où la borne de zoom peut prétendre.
             */
            largeurMax: 0,
            // Un geste de zoom vient d'être refusé : on le dit, brièvement.
            refus: false,
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

        /** Besoins des appareils, du plus gourmand au moins gourmand. */
        besoins() {
            return this.cibles
                .map((c) => Math.min(Math.round(c.css * c.dpr), this.plafondServeur))
                .sort((x, y) => y - x)
        },

        /** Pixels réclamés par l'appareil le plus gourmand, pour l'affichage. */
        exigence() {
            return this.besoins[0] || 0
        },

        /**
         * Largeur sous laquelle le zoom se bloque.
         *
         * Le besoin le plus élevé que la photo d'origine peut ENCORE honorer,
         * et non l'exigence absolue. Une photo de 700 px ne pourra jamais
         * servir un téléphone : y poser la borne du téléphone bloquerait le
         * zoom dès le premier cran, sur une photo pourtant utilisable. On
         * descend donc l'échelle des besoins jusqu'au premier qu'elle peut
         * tenir — l'ordinateur, le plus souvent — pour garder un garde-fou
         * au lieu d'en abandonner l'idée.
         *
         * Zéro quand la photo est trop petite même pour le moins exigeant :
         * il n'y a alors plus rien à protéger, et l'avertissement prend le
         * relais.
         */
        plancher() {
            return this.besoins.find((b) => b <= this.largeurMax) || 0
        },

        /** Dimensions de sortie, largeur plafonnée à ce que le serveur sert. */
        sortie() {
            if (!this.zone) return null

            const f = Math.min(1, this.plafondServeur / this.zone.width)

            return {
                width: Math.round(this.zone.width * f),
                height: Math.round(this.zone.height * f),
            }
        },

        /**
         * Verdict par appareil.
         *
         * Comparé à ce que le site peut réellement fournir, et non au besoin
         * théorique de l'appareil. Un téléphone qui réclame 1218 px n'en
         * recevra jamais que 1200 : l'annoncer « juste » en permanence serait
         * une alerte qu'aucun cadrage ne peut éteindre, donc une alerte qu'on
         * apprend à ignorer.
         *
         * « juste » plutôt que « flou » entre 75 et 100 % du besoin : à cette
         * distance l'œil ne voit rien sur une photo de coupe, et refuser un
         * cadrage utilisable pour quelques pixels serait pénible.
         */
        verdicts() {
            return this.cibles.map((c) => {
                const requis = Math.round(c.css * c.dpr)

                if (!this.sortie) return { nom: c.nom, requis, etat: null }

                const part = this.sortie.width / Math.min(requis, this.plafondServeur)

                return {
                    nom: c.nom,
                    requis,
                    etat: part >= 1 ? 'net' : (part >= 0.75 ? 'juste' : 'flou'),
                }
            })
        },

        /** Zone trop petite pour au moins un appareil. */
        tropPetit() {
            return this.verdicts.some((v) => v.etat && v.etat !== 'net')
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
        // Démontage pendant que la fenêtre est ouverte — changement de page
        // par exemple : sans ce filet, le corps resterait bloqué.
        if (this.ouvert) document.body.style.overflow = this.defilementInitial ?? ''

        window.removeEventListener('keydown', this.auClavier)
        clearTimeout(this.minuteurRefus)
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
            this.ouvrir()
        },

        /**
         * Ouverture, défilement de fond verrouillé.
         *
         * Sans cela, la molette passée à côté du cadre fait défiler le
         * formulaire derrière le voile : on rouvre les yeux sur une page qui
         * a bougé, et la fenêtre semble avoir sauté.
         */
        ouvrir() {
            this.defilementInitial = document.body.style.overflow
            document.body.style.overflow = 'hidden'
            this.ouvert = true
            this.zone = null
            this.largeurMax = 0

            // Le <img> n'existe qu'une fois la fenêtre rendue
            this.$nextTick(() => this.demarrer())
        },

        /** Échelle d'affichage de l'image : pixels à l'écran par pixel d'origine. */
        echelle() {
            const i = this.cropper.getImageData()

            return i.width / i.naturalWidth
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
                /*
                 * Cadre fixe, photo mobile dessous.
                 *
                 * Au réglage d'origine, le cadre captait le geste et se
                 * déplaçait dans la portion d'image visible. Comme il occupe
                 * déjà toute la hauteur de la fenêtre, il n'avait aucun jeu
                 * vertical : une fois zoomé, le haut et le bas de la photo
                 * devenaient purement inatteignables — mesuré, le déplacement
                 * vertical ne bougeait pas d'un pixel.
                 *
                 * `dragMode: move` seul ne suffisait pas : le cadre restait
                 * prioritaire sur le geste en son centre, et on ne déplaçait
                 * la photo qu'en partant de la zone sombre autour. Il faut lui
                 * retirer la main — `cropBoxMovable` — pour que le glissement
                 * atteigne la photo où qu'il commence.
                 *
                 * Le cadre n'est plus redimensionnable non plus, et c'est un
                 * gain : le zoom devient le SEUL moyen de resserrer, donc le
                 * seul chemin vers une perte de définition. Or le zoom est
                 * borné. La qualité cesse d'être une recommandation pour
                 * devenir une garantie.
                 */
                dragMode: 'move',
                cropBoxMovable: false,
                cropBoxResizable: false,
                movable: true,
                zoomable: true,
                responsive: true,
                checkOrientation: true,
                // Molette adoucie : au pas d'origine, un cran de roulette
                // franchissait d'un coup toute la marge de qualité.
                wheelZoomRatio: 0.05,
                /*
                 * Les trois aperçus. Le recadreur y recopie la zone retenue et
                 * la met à l'échelle de chaque cadre, en direct. Les éléments
                 * sont passés en dur plutôt que par sélecteur : un sélecteur
                 * attraperait aussi les aperçus d'un autre sélecteur de la même
                 * page.
                 */
                preview: this.$refs.apercus || [],
                ready: () => {
                    /*
                     * Plus grande zone que cette photo peut offrir. Avec des
                     * proportions imposées, elle est bornée par le côté le
                     * plus contraignant : une photo panoramique recadrée en
                     * portrait est limitée par sa hauteur, pas sa largeur.
                     */
                    const i = this.cropper.getImageData()

                    this.largeurMax = this.ratio
                        ? Math.min(i.naturalWidth, i.naturalHeight * this.ratio)
                        : i.naturalWidth
                },
                /*
                 * Zoom : le seul événement annulable du recadreur. La zone
                 * retenue après le geste vaut cadre affiché / nouvelle échelle
                 * — vérifié en navigateur. Sous l'exigence, on refuse le cran.
                 */
                zoom: (e) => {
                    if (!this.plancher) return

                    const cadre = this.cropper.getCropBoxData().width
                    const apres = cadre / e.detail.ratio

                    if (e.detail.ratio > e.detail.oldRatio && apres < this.plancher) {
                        e.preventDefault()
                        this.signalerRefus()
                    }
                },
                /*
                 * Déplacement, redimensionnement, zoom.
                 *
                 * On se contente d'enregistrer la zone. Une version précédente
                 * corrigeait ici le cadre devenu trop petit, en lui rendant de
                 * force sa largeur minimale — et cette correction interdisait
                 * de recadrer sur toute la photo. En déplaçant l'image, le
                 * cadre vient toucher le bord du visible et le recadreur le
                 * rétrécit d'un cheveu ; la correction se déclenchait alors et
                 * ramenait tout en arrière. Le déplacement vertical était
                 * purement et simplement bloqué.
                 *
                 * La borne de qualité tient donc au seul endroit où elle ne
                 * coûte rien : le zoom, dont l'événement est annulable. Sous
                 * le plancher par un autre chemin — une poignée tirée vers
                 * l'intérieur — l'avertissement prend le relais plutôt que la
                 * contrainte. Rendre la photo entièrement cadrable valait bien
                 * de laisser ce chemin ouvert.
                 */
                crop: (e) => {
                    this.zone = { width: e.detail.width, height: e.detail.height }
                },
            })
        },

        /** Ne ferme que si l'appui ET le relâchement sont sur le voile. */
        fermerSiFond() {
            const depuisLeFond = this.debutFond

            this.debutFond = false

            if (depuisLeFond) this.fermer()
        },

        /** Mention passagère, pour que le refus ne paraisse pas être un blocage. */
        signalerRefus() {
            this.refus = true
            clearTimeout(this.minuteurRefus)
            this.minuteurRefus = setTimeout(() => { this.refus = false }, 2200)
        },

        detruire() {
            this.cropper?.destroy()
            this.cropper = null
        },

        libererSource() {
            if (this.source) URL.revokeObjectURL(this.source)

            this.source = null
        },

        zoomer(pas) {
            this.cropper?.zoom(pas)
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
            this.refus = false
            document.body.style.overflow = this.defilementInitial ?? ''
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
             *
             * Et ce sont les mêmes dimensions que celles annoncées dans la
             * fenêtre : un second calcul ici afficherait un chiffre et en
             * enregistrerait un autre, à un pixel près.
             */
            const zone = this.cropper.getData()
            const f = Math.min(1, this.plafondServeur / zone.width)
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
            this.ouvrir()
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

        <!--
            Fenêtre de recadrage, déportée dans <body>.

            Elle ne peut pas rester dans la carte : `.card` porte un
            backdrop-filter, et un filtre d'arrière-plan fait de l'élément le
            référentiel de ses descendants en position: fixed. Le voile se
            calait donc sur la carte — 1553 px de haut sur un formulaire
            long — au lieu de l'écran, et la fenêtre s'ouvrait à près de
            200 px au-dessus du centre, titre coupé.

            Montée seulement à l'ouverture : Cropper mesure son conteneur au
            démarrage et renverrait des dimensions nulles sur un élément caché.

            Fermeture au clic sur le voile, mais SEULEMENT si le geste y a
            aussi commencé. Le modificateur .self ne suffit pas : le navigateur
            émet le clic sur le plus proche ancêtre commun entre l'appui et le
            relâchement. Un glissement parti de la photo et fini sur le voile
            désigne donc le voile, et la fenêtre se fermait en effaçant le
            cadrage en cours — ce qui arrive sans arrêt depuis que l'on fait
            coulisser la photo sur de longues distances.
        -->
        <Teleport to="body">
        <div v-if="ouvert" class="crop-fond" role="dialog" aria-modal="true"
             aria-label="Recadrer l'image"
             @pointerdown="debutFond = $event.target === $event.currentTarget"
             @click.self="fermerSiFond">
            <div class="crop-fenetre">
                <p class="crop-titre">Recadrer</p>

                <div class="crop-corps">
                    <div class="crop-gauche">
                        <div class="crop-scene">
                            <img ref="image" :src="source" alt="">
                        </div>

                        <div class="crop-outils">
                            <button type="button" class="btn-icon" aria-label="Dézoomer" @click="zoomer(-0.1)">
                                <Icon name="minus" :size="18" />
                            </button>
                            <button type="button" class="btn-icon" aria-label="Zoomer" @click="zoomer(0.1)">
                                <Icon name="plus" :size="18" />
                            </button>
                            <span class="crop-sep"></span>
                            <button type="button" class="btn-icon" aria-label="Pivoter à gauche" @click="pivoter(-90)">
                                <Icon name="rotateLeft" :size="18" />
                            </button>
                            <button type="button" class="btn-icon" aria-label="Pivoter à droite" @click="pivoter(90)">
                                <Icon name="rotateRight" :size="18" />
                            </button>
                            <span class="crop-sep"></span>
                            <button type="button" class="btn btn--ghost btn--sm" @click="reinitialiser">Tout remettre</button>
                        </div>

                        <!-- Le refus de zoom doit s'expliquer sur-le-champ : sans
                             ça le bouton paraît cassé. -->
                        <p class="crop-refus" v-if="refus">
                            <Icon name="alert" :size="15" />
                            <span>Zoom arrêté là : sous {{ plancher }} px de large,
                                  la photo deviendrait floue à l'affichage.</span>
                        </p>
                    </div>

                    <!--
                        Aperçu par appareil.

                        Le cadrage est le MÊME sur les trois : aucune de nos
                        proportions d'affichage ne change avec la largeur de
                        l'écran — vérifié sur la feuille de style. Ce qui change
                        est la définition réclamée, et le classement surprend :
                        le téléphone demande trois fois plus de pixels que le
                        grand écran, parce qu'il en affiche trois par pixel CSS.
                    -->
                    <div class="crop-droite">
                        <p class="crop-sous-titre">Rendu sur le site</p>

                        <div class="crop-apercus">
                            <!-- Itération sur `cibles` et non sur `verdicts` :
                                 les verdicts dépendent de la zone retenue, qui
                                 n'existe qu'au premier événement du recadreur —
                                 c'est-à-dire APRÈS sa construction. Les cadres
                                 auraient alors été absents au moment où on les
                                 lui passe, et les aperçus seraient restés
                                 vides. -->
                            <figure v-for="(c, i) in cibles" :key="c.nom" class="crop-apercu">
                                <div class="crop-apercu-cadre" ref="apercus" :style="cadreStyle"></div>
                                <figcaption>
                                    <strong>{{ c.nom }}</strong>
                                    <span class="crop-etat" :class="`is-${verdicts[i].etat}`" v-if="verdicts[i].etat">
                                        {{ verdicts[i].etat === 'net' ? 'Net'
                                           : (verdicts[i].etat === 'juste' ? 'Juste' : 'Flou') }}
                                    </span>
                                    <em>{{ verdicts[i].requis }} px requis</em>
                                </figcaption>
                            </figure>
                        </div>

                        <p class="crop-taille" :class="{ 'is-faible': tropPetit }" v-if="sortie">
                            <Icon v-if="tropPetit" name="alert" :size="15" />
                            <span>
                                Enregistré en {{ sortie.width }} × {{ sortie.height }} px.
                                <template v-if="tropPetit">
                                    La photo d'origine ne contient pas assez de pixels pour
                                    tous les écrans — élargissez le cadre, ou reprenez une
                                    photo plus grande.
                                </template>
                            </span>
                        </p>

                        <p class="field-help">
                            Le cadrage est identique sur les trois : seule la finesse
                            change. Le téléphone est le plus exigeant — il affiche
                            trois pixels là où un écran de bureau en affiche un.
                            Le site ne sert jamais plus de {{ plafondServeur }} px de large.
                        </p>
                    </div>
                </div>

                <div class="row-actions">
                    <button type="button" class="btn btn--solid btn--sm" :disabled="occupe" @click="valider">
                        {{ occupe ? 'Découpe…' : 'Valider le cadrage' }}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" @click="fermer">Annuler</button>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>
