<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { formatDateTime, formatDateLong, formatMonth, money, STUDIO_TZ } from '@/date'
import { statusLabel, statusClass, statusIcon, isPending, isWarning, STATUS_FILTERS, STATUS_LEGEND } from '@/status'

/**
 * Rendez-vous du mois : validation, refus, contre-proposition de 1 à 3
 * créneaux, passage en réalisé.
 */
export default {
    layout: AdminLayout,
    components: { Icon, Link },

    props: {
        view:         { type: String, default: 'month' },
        date:         { type: String, required: true },
        period:       { type: Object, default: () => ({}) },
        status:        { type: Array, default: () => [] },
        statusAll:     { type: Array, default: () => [] },
        statusDefault: { type: Array, default: () => [] },
        services:      { type: Array, default: () => [] },
        search:       { type: String, default: null },
        day:          { type: String, default: null },
        focus:        { type: Object, default: null },
        appointments: { type: Array,  default: () => [] },
        calendar:     { type: Array,  default: () => [] },
        legend:       { type: Array,  default: () => [] },
    },

    data() {
        return {
            statusFilters: STATUS_FILTERS,
            colsHeight: 0,
            filtersOpen: false,
            statusLegend: STATUS_LEGEND,
            // Le jour vient du serveur : il survit au rechargement de la page
            q: this.search || '',
            counterFor: null,
            counterNote: '',
            // Créneaux libres reçus du serveur, groupés par jour
            counterSlots: {},
            counterDay: null,
            counterPicked: [],
            counterLoading: false,
            refuseFor: null,
            refuseForm: useForm({ reason: '' }),
            cancelFor: null,
            cancelForm: useForm({ reason: '' }),
        }
    },

    computed: {
        isMonth() { return this.view === 'month' },
        isWeek()  { return this.view === 'week' },
        isDay()   { return this.view === 'day' },

        /** Intitulé de la période affichée, propre à chaque échelle. */
        periodLabel() {
            if (this.isDay) return formatDateLong(this.date)

            if (this.isWeek) {
                // Midi plutôt que minuit : une date nue est lue en UTC et
                // basculerait d'un jour une partie de l'année.
                const court = (iso) => new Date(iso + 'T12:00:00').toLocaleDateString('fr-FR', {
                    timeZone: STUDIO_TZ, day: 'numeric', month: 'short',
                })

                return `${court(this.period.from)} – ${court(this.period.to)}`
            }

            return formatMonth(this.date)
        },

        weekdays() {
            return ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
        },

        /**
         * Cases vides avant le 1er du mois.
         *
         * Sans ce décalage, le 1er se placerait toujours sous « Lun » quel que
         * soit le jour réel, et toute la grille serait fausse.
         */
        leadingBlanks() {
            const first = this.calendar[0]
            if (!first) return 0

            const [y, m, d] = first.date.split('-').map(Number)

            // Construction explicite : new Date('2026-09-01') serait interprété
            // en UTC et pourrait basculer d'un jour selon le fuseau.
            const weekday = new Date(y, m - 1, d).getDay()

            // getDay() compte à partir de dimanche ; la semaine française
            // commence lundi.
            return (weekday + 6) % 7
        },

        selectedDayLabel() {
            return this.day ? formatDateLong(this.day) : null
        },

        counterDays() {
            return Object.keys(this.counterSlots)
        },

        /**
         * Y a-t-il au moins un filtre actif ?
         *
         * La sélection par défaut n'en est pas un : l'annoncer ferait croire à
         * un réglage qu'on aurait posé soi-même.
         */
        hasFilters() {
            return !this.sameAsDefault || !this.allServices || Boolean(this.search || this.day)
        },

        /** Toutes les prestations retenues : c'est l'état sans filtre. */
        allServices() {
            return this.legend.length === this.services.length
        },

        sameAsDefault() {
            return this.status.length === this.statusDefault.length
                && this.statusDefault.every((v) => this.status.includes(v))
        },

        /** Libellés des statuts retenus, pour le bandeau replié. */
        statusSummary() {
            if (this.sameAsDefault) return 'Confirmés et en attente'
            if (!this.status.length) return 'Aucun statut'
            if (this.status.length === this.statusAll.length) return 'Tous les statuts'

            return this.statusFilters
                .filter((f) => f.values.some((v) => this.status.includes(v)))
                .map((f) => f.label)
                .join(', ')
        },

        /**
         * Amplitude horaire du planning.
         *
         * Elle épouse exactement les disponibilités définies pour les jours
         * affichés : pas d'arrondi à l'heure pleine, sinon une ouverture à
         * 9h30 afficherait une tranche 9h–9h30 qui n'existe pas, et pas de
         * plage par défaut, qui montrerait des heures sur un jour fermé.
         */
        timeline() {
            let min = Infinity
            let max = -Infinity

            this.calendar.forEach((jour) => (jour.ranges || []).forEach(([debut, fin]) => {
                if (debut < min) min = debut
                if (fin > max) max = fin
            }))

            // Un rendez-vous posé hors des plages ouvertes — horaire
            // exceptionnel, créneau de nuit, disponibilité retirée après coup —
            // doit rester visible plutôt que d'être dessiné hors cadre.
            this.appointments.forEach((a) => {
                if (a.start_min < min) min = a.start_min
                if (a.start_min + a.duration_min > max) max = a.start_min + a.duration_min
            })

            if (min === Infinity) return { from: 0, to: 0, span: 0 }

            return { from: min, to: max, span: Math.max(max - min, 1) }
        },

        /**
         * Prestations présentes chaque jour, sans doublon.
         *
         * Quatre pastilles au plus : c'est un repère de composition, pas un
         * décompte — le nombre de créneaux pris est déjà sous la jauge.
         */
        dotsByDay() {
            const parJour = {}

            this.appointments.forEach((a) => {
                const couleurs = parJour[a.date] ||= []

                if (!couleurs.includes(a.service_color)) couleurs.push(a.service_color)
            })

            return parJour
        },

        /** Rien d'ouvert et rien de réservé : il n'y a pas de planning à tracer. */
        hasTimeline() {
            return this.timeline.span > 1 || this.timeline.to > this.timeline.from
        },

        /**
         * Graduations : les bornes réelles d'ouverture, plus les heures pleines
         * entre les deux. Une heure pleine trop proche d'une borne est écartée,
         * les deux libellés se chevaucheraient.
         */
        hourMarks() {
            const { from, to } = this.timeline

            if (!this.hasTimeline) return []

            const marks = [from]

            for (let m = Math.ceil(from / 60) * 60; m < to; m += 60) {
                if (Math.abs(m - from) >= 20 && Math.abs(to - m) >= 20) marks.push(m)
            }

            marks.push(to)

            return marks
        },

        /**
         * Rendez-vous positionnés, jour par jour.
         *
         * Deux rendez-vous qui se chevauchent — une demande en attente posée
         * sur un créneau déjà confirmé, par exemple — sont répartis en
         * colonnes plutôt que superposés, sinon l'un masque l'autre.
         */
        timelineDays() {
            return this.calendar.map((jour) => {
                const evenements = this.appointments
                    .filter((a) => a.date === jour.date)
                    .slice()
                    .sort((x, y) => x.start_min - y.start_min)

                const places = []
                let groupe = []
                let finsDeColonne = []
                let finDuGroupe = -1

                // Le partage de largeur ne vaut que pour le groupe concerné :
                // un rendez-vous isolé en fin de journée doit rester pleine
                // largeur même si deux autres se chevauchaient le matin.
                const cloturer = () => {
                    const total = finsDeColonne.length

                    groupe.forEach((e) => places.push({ ...e, colonnes: total }))

                    groupe = []
                    finsDeColonne = []
                    finDuGroupe = -1
                }

                evenements.forEach((a) => {
                    const fin = a.start_min + a.duration_min

                    if (a.start_min >= finDuGroupe) cloturer()

                    let colonne = finsDeColonne.findIndex((f) => f <= a.start_min)

                    if (colonne === -1) {
                        colonne = finsDeColonne.length
                        finsDeColonne.push(fin)
                    } else {
                        finsDeColonne[colonne] = fin
                    }

                    finDuGroupe = Math.max(finDuGroupe, fin)
                    groupe.push({ ...a, colonne })
                })

                cloturer()

                return { ...jour, evenements: places }
            })
        },
    },

    created() {
        // Propriétés hors data : un nœud du DOM n'a rien à faire dans le
        // système de réactivité.
        this.ro = null
        this.roEl = null
    },

    beforeUnmount() {
        this.ro?.disconnect()
    },

    updated() {
        // La zone n'existe pas en vue mois : il faut réobserver à chaque
        // changement d'échelle.
        this.mesurerColonnes()
    },

    mounted() {
        this.mesurerColonnes()

        if (!this.focus) return

        // Le rendez-vous ciblé est amené à l'écran et signalé. nextTick :
        // la liste n'est pas encore rendue au moment du montage.
        this.$nextTick(() => {
            const el = this.$refs['appointment' + this.focus.id]?.[0]
            if (!el) return

            el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        })
    },

    methods: {
        formatDateTime,
        formatDateLong,
        money,
        statusLabel,
        statusClass,
        statusIcon,

        /**
         * Hauteur réelle de la zone de planning.
         *
         * Les pavés sont positionnés en pourcentage, mais ce qu'on peut y
         * écrire dépend de leur hauteur en pixels : mieux vaut une ligne
         * entière que trois lignes tranchées au milieu.
         */
        mesurerColonnes() {
            const el = this.$refs.cols

            if (el === this.roEl) return

            this.ro?.disconnect()
            this.roEl = el || null

            if (!el) return

            const relever = () => {
                if (el.clientHeight !== this.colsHeight) this.colsHeight = el.clientHeight
            }

            relever()
            this.ro = new ResizeObserver(relever)
            this.ro.observe(el)
        },

        density(duree) {
            const px = (duree / this.timeline.span) * (this.colsHeight || 560)

            return px < 34 ? 'is-tiny' : (px < 58 ? 'is-short' : 'is-full')
        },

        /**
         * Noir ou blanc selon la clarté du fond.
         *
         * Une seule couleur de texte ne peut pas convenir à la fois au jaune
         * vif et au violet sombre : la luminance tranche.
         */
        ink(hex) {
            if (!/^#[0-9A-Fa-f]{6}$/.test(hex || '')) return '#0A1206'

            const n = parseInt(hex.slice(1), 16)
            const lin = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((v) => {
                v /= 255

                return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)
            })

            return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2] > 0.42 ? '#0A1206' : '#FFFFFF'
        },

        /**
         * Tranches horaires hors disponibilités, complément des plages ouvertes.
         *
         * Ce sont elles qu'on hachure : sans repère, une heure fermée se lit
         * comme une heure libre.
         */
        closedBands(jour) {
            const { from, to } = this.timeline
            const bandes = []
            let curseur = from

            ;(jour.ranges || []).slice().sort((a, b) => a[0] - b[0]).forEach(([debut, fin]) => {
                if (debut > curseur) bandes.push([curseur, debut])

                curseur = Math.max(curseur, fin)
            })

            if (curseur < to) bandes.push([curseur, to])

            return bandes
        },

        dotsFor(date) {
            return (this.dotsByDay[date] || []).slice(0, 4)
        },

        /**
         * Couleur de prestation convertie en fond translucide.
         *
         * Le pavé reste lisible sur le fond sombre, et le texte blanc par
         * dessus garde son contraste quelle que soit la teinte choisie.
         */
        rgba(hex, alpha) {
            if (!/^#[0-9A-Fa-f]{6}$/.test(hex || '')) return `rgba(105, 230, 75, ${alpha})`

            const n = parseInt(hex.slice(1), 16)

            return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`
        },

        /** Le statut passe par la forme du pavé, la prestation par sa couleur. */
        eventClass(a) {
            return [
                this.density(a.duration_min),
                {
                    'is-pending': isPending(a.status),
                    'is-off':     isWarning(a.status),
                },
            ]
        },

        /**
         * Un seul point d'entrée pour tous les filtres.
         *
         * Chacun ne fournit que ce qu'il change ; le reste est conservé. Sans
         * cela, chercher un client effacerait le filtre de statut, et changer
         * de mois effacerait la recherche.
         */
        applyFilters(changes = {}) {
            const params = {
                view:   this.view === 'day' ? undefined : this.view,
                date:   this.date,
                status:  this.status,
                service: this.services,
                q:      this.q || undefined,
                day:    this.day || undefined,
                ...changes,
            }

            // Les valeurs vides sont retirées pour garder une URL lisible
            Object.keys(params).forEach((k) => {
                if (!params[k] || (Array.isArray(params[k]) && !params[k].length)) delete params[k]
            })

            /*
             * Sélection par défaut : c'est celle du serveur, inutile de
             * l'écrire. Sélection vide : le paramètre disparaîtrait de l'URL
             * et le serveur rappliquerait le défaut — on envoie donc une
             * valeur qui ne correspond à aucun statut, pour dire « aucun ».
             */
            if (this.memeQueDefaut(params.status)) delete params.status
            else if (!params.status) params.status = ['aucun']

            if (params.service && params.service.length === this.legend.length) delete params.service
            else if (!params.service) params.service = ['aucune']

            router.get(route('admin.appointments.index'), params, {
                preserveScroll: true,
                preserveState: true,
            })
        },

        /** Avance ou recule d'un pas : un jour, une semaine ou un mois. */
        shift(delta) {
            const [y, m, j] = this.date.split('-').map(Number)
            const d = new Date(y, m - 1, j)

            if (this.isDay) {
                d.setDate(d.getDate() + delta)
            } else if (this.isWeek) {
                d.setDate(d.getDate() + 7 * delta)
            } else {
                // Le 1er d'abord : sans cela, le 31 janvier + 1 mois donne le
                // 3 mars, février n'ayant pas de 31e jour.
                d.setDate(1)
                d.setMonth(d.getMonth() + delta)
            }

            // La période change : le jour filtré n'en fait peut-être plus partie.
            this.applyFilters({ date: this.toYmd(d), day: undefined })
        },

        /** Y-m-d construit à la main : toISOString repasserait en UTC. */
        toYmd(d) {
            const p = (n) => String(n).padStart(2, '0')

            return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
        },

        /**
         * Changement d'échelle.
         *
         * Le jour filtré devient l'ancre : passer de « mois » à « jour » après
         * avoir cliqué le 14 ouvre bien le 14, pas le 1er.
         */
        setView(view) {
            if (view === this.view) return

            this.applyFilters({
                view: view === 'day' ? undefined : view,
                date: this.day || this.date,
                day:  undefined,
            })
        },

        /** Position verticale, en pourcentage de l'amplitude affichée. */
        topPct(minute) {
            return ((minute - this.timeline.from) / this.timeline.span) * 100
        },

        rangeStyle([debut, fin]) {
            return { top: `${this.topPct(debut)}%`, height: `${((fin - debut) / this.timeline.span) * 100}%` }
        },

        eventStyle(a) {
            const largeur = 100 / a.colonnes
            const place = {
                top:    `${this.topPct(a.start_min)}%`,
                height: `${Math.max((a.duration_min / this.timeline.span) * 100, 2.5)}%`,
                left:   `${a.colonne * largeur}%`,
                width:  `${largeur}%`,
            }

            // Annulé ou non honoré : contour vide. Un pavé plein se lirait
            // comme un créneau qui tient, et l'estomper rendait le nom illisible.
            if (isWarning(a.status)) {
                return {
                    ...place,
                    background:  this.rgba(a.service_color, 0.14),
                    color:       a.service_color,
                    borderColor: a.service_color,
                }
            }

            const encre = this.ink(a.service_color)

            return { ...place, background: a.service_color, color: encre, borderColor: encre }
        },

        /** « 9h » / « 14h30 » à partir de minutes depuis minuit. */
        hourLabel(minute) {
            const h = Math.floor(minute / 60)
            const m = minute % 60

            return m ? `${h}h${String(m).padStart(2, '0')}` : `${h}h`
        },

        /** Un clic sur un bloc amène la fiche correspondante à l'écran. */
        focusAppointment(id) {
            const el = this.$refs['appointment' + id]?.[0]

            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        },

        memeQueDefaut(liste) {
            return Array.isArray(liste)
                && liste.length === this.statusDefault.length
                && this.statusDefault.every((v) => liste.includes(v))
        },

        /** Une entrée de légende couvre parfois deux statuts : active si les deux le sont. */
        statusOn(groupe) {
            return groupe.values.every((v) => this.status.includes(v))
        },

        /**
         * Clic sur la légende : le statut sort ou rentre, et la page se
         * recharge aussitôt. Pas de bouton « Appliquer » — une légende qui
         * demande une validation n'est plus une légende.
         */
        toggleStatus(groupe) {
            const suite = this.statusOn(groupe)
                ? this.status.filter((v) => !groupe.values.includes(v))
                : [...new Set([...this.status, ...groupe.values])]

            this.applyFilters({ status: suite })
        },

        serviceOn(id) {
            return this.services.includes(id)
        },

        toggleService(id) {
            const suite = this.serviceOn(id)
                ? this.services.filter((v) => v !== id)
                : [...this.services, id]

            this.applyFilters({ service: suite })
        },

        /** Un clic sur l'intitulé de la rangée remet tout en service. */
        allStatuses() {
            this.applyFilters({ status: this.statusAll })
        },

        allPrestations() {
            this.applyFilters({ service: this.legend.map((p) => p.id) })
        },

        resetFilters() {
            this.q = ''
            this.applyFilters({
                status:  this.statusDefault,
                service: this.legend.map((p) => p.id),
                q:       undefined,
                day:     undefined,
            })
        },

        confirm(id) {
            router.post(route('admin.appointments.confirm', id), {}, { preserveScroll: true })
        },

        complete(id) {
            router.post(route('admin.appointments.complete', id), {}, { preserveScroll: true })
        },

        /**
         * Absence du client.
         *
         * Confirmation demandée : l'action est irréversible depuis l'écran et
         * peut, au cumul, suspendre l'accès du client à la réservation.
         */
        noShow(a) {
            if (!confirm(`Marquer ${a.client} comme non venu ?\n\nCette absence compte dans le seuil de blocage automatique.`)) {
                return
            }

            router.post(route('admin.appointments.noshow', a.id), {}, { preserveScroll: true })
        },

        openCancel(id) {
            this.cancelFor = id
            this.cancelForm.reason = ''
        },

        submitCancel() {
            this.cancelForm.post(route('admin.appointments.cancel', this.cancelFor), {
                preserveScroll: true,
                onSuccess: () => { this.cancelFor = null },
            })
        },

        openRefuse(id) {
            this.refuseFor = id
            this.refuseForm.reason = ''
        },

        submitRefuse() {
            this.refuseForm.post(route('admin.appointments.refuse', this.refuseFor), {
                preserveScroll: true,
                onSuccess: () => { this.refuseFor = null },
            })
        },

        async openCounter(id) {
            this.counterFor = id
            this.counterNote = ''
            this.counterPicked = []
            this.counterSlots = {}
            this.counterDay = null
            this.counterLoading = true

            try {
                const response = await fetch(route('admin.appointments.slots', id), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })

                if (!response.ok) throw new Error(response.status)

                const data = await response.json()

                this.counterSlots = data.slots || {}
                this.counterDay = Object.keys(this.counterSlots)[0] || null
            } catch {
                // Échec du chargement : on referme plutôt que d'afficher un
                // sélecteur vide, qui laisserait croire à l'absence de créneaux.
                this.counterFor = null
                alert('Impossible de charger les créneaux disponibles.')
            } finally {
                this.counterLoading = false
            }
        },

        /** Trois créneaux au maximum, un second clic retire la sélection. */
        togglePick(day, time) {
            const value = `${day} ${time}:00`
            const i = this.counterPicked.indexOf(value)

            if (i !== -1) {
                this.counterPicked.splice(i, 1)
            } else if (this.counterPicked.length < 3) {
                this.counterPicked.push(value)
            }
        },

        isPicked(day, time) {
            return this.counterPicked.includes(`${day} ${time}:00`)
        },

        submitCounter() {
            if (!this.counterPicked.length) return

            router.post(route('admin.appointments.counter', this.counterFor), {
                slots: this.counterPicked,
                note: this.counterNote,
            }, {
                preserveScroll: true,
                onSuccess: () => { this.counterFor = null },
            })
        },

        dayNumber(date) {
            return Number(date.split('-')[2])
        },

        /** « lun », « mar »… en-tête des colonnes du planning. */
        dayName(date) {
            return new Date(date + 'T12:00:00').toLocaleDateString('fr-FR', {
                timeZone: STUDIO_TZ, weekday: 'short',
            })
        },

        /** Depuis la vue semaine, un clic sur une colonne ouvre la journée. */
        openDay(date) {
            this.applyFilters({ view: 'day', date, day: undefined })
        },

        /** Un second clic sur le même jour revient au mois entier. */
        selectDay(dayCell) {
            this.applyFilters({ day: this.day === dayCell.date ? undefined : dayCell.date })
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Rendez-vous</h1>
    <p class="admin-sub">{{ periodLabel }} — {{ appointments.length }} rendez-vous.</p>

    <section class="card admin-card">
        <!-- Les filtres commandent le planning ET la liste : ils sont donc en
             tête de page, pas au-dessus de la seule liste. Repliés par défaut,
             la légende suffisant au réglage courant. -->
        <div class="day-filter" :class="{ 'is-plain': !hasFilters }">
            <button type="button" class="btn btn--ghost btn--sm"
                    :aria-expanded="filtersOpen" @click="filtersOpen = !filtersOpen">
                <Icon name="settings" :size="16" />
                {{ filtersOpen ? 'Masquer les filtres' : 'Filtres' }}
            </button>

            <span class="filter-resume">
                {{ appointments.length }} rendez-vous · {{ statusSummary }}
                <template v-if="!allServices"> · {{ services.length }} prestation(s)</template>
                <template v-if="day"> · {{ selectedDayLabel }}</template>
                <template v-if="search"> · « {{ search }} »</template>
                <template v-if="focus"> · demande signalée plus bas</template>
            </span>

            <button v-if="hasFilters" type="button" class="btn btn--ghost btn--sm"
                    @click="resetFilters">Réinitialiser</button>
        </div>

        <div class="filter-bar" v-if="filtersOpen">
            <label class="field">
                <span class="field-label">Client</span>
                <input
                    type="text"
                    v-model="q"
                    placeholder="Nom, email ou téléphone"
                    @keyup.enter="applyFilters({ q: q || undefined })"
                    @search="applyFilters({ q: q || undefined })"
                >
            </label>

            <label class="field">
                <span class="field-label">Jour</span>
                <input type="date" :value="day || ''"
                       @change="applyFilters({ day: $event.target.value || undefined })">
            </label>

            <button type="button" class="btn btn--solid btn--sm"
                    @click="applyFilters({ q: q || undefined })">Filtrer</button>
        </div>

        <div class="seg-modes">
            <button type="button" :class="{ 'is-active': isDay }"   @click="setView('day')">Jour</button>
            <button type="button" :class="{ 'is-active': isWeek }"  @click="setView('week')">Semaine</button>
            <button type="button" :class="{ 'is-active': isMonth }" @click="setView('month')">Mois</button>
        </div>

        <div class="period-nav">
            <button type="button" class="btn-icon" aria-label="Période précédente" @click="shift(-1)">
                <Icon name="left" :size="20" />
            </button>
            <span class="admin-card-title" style="margin: 0">{{ periodLabel }}</span>
            <button type="button" class="btn-icon" aria-label="Période suivante" @click="shift(1)">
                <Icon name="right" :size="20" />
            </button>
        </div>

        <!-- Deux échelles de lecture superposées : la couleur dit quelle
             prestation, l'icône dit où en est la demande. Chaque entrée est un
             interrupteur — une légende qui désigne les couleurs peut aussi
             bien servir à les éteindre. -->
        <div class="legend">
            <div class="legend-group" v-if="legend.length">
                <button type="button" class="legend-title" title="Tout réafficher"
                        @click="allPrestations">Prestations</button>
                <button
                    v-for="presta in legend"
                    :key="presta.id"
                    type="button"
                    class="legend-item"
                    :class="{ 'is-off': !serviceOn(presta.id) }"
                    :aria-pressed="serviceOn(presta.id)"
                    @click="toggleService(presta.id)"
                >
                    <span class="chip" :style="{ background: presta.color }"></span>{{ presta.name }}
                </button>
            </div>

            <div class="legend-group">
                <button type="button" class="legend-title" title="Tout réafficher"
                        @click="allStatuses">Statut</button>
                <button
                    v-for="groupe in statusFilters"
                    :key="groupe.label"
                    type="button"
                    class="legend-item"
                    :class="{ 'is-off': !statusOn(groupe) }"
                    :aria-pressed="statusOn(groupe)"
                    @click="toggleStatus(groupe)"
                >
                    <Icon :name="groupe.icon" :size="14" />{{ groupe.label }}
                </button>
            </div>
        </div>

        <!-- Vue mois : charge de chaque journée, un clic filtre la liste. -->
        <div class="month-grid" v-if="isMonth">
            <span v-for="label in weekdays" :key="label" class="month-head">{{ label }}</span>

            <!-- Cases vides pour aligner le 1er sur le bon jour de la semaine -->
            <span v-for="n in leadingBlanks" :key="`blank${n}`" class="month-blank"></span>

            <button
                v-for="cell in calendar"
                :key="cell.date"
                type="button"
                class="month-cell"
                :class="{ 'is-closed': cell.open_min === 0, 'is-selected': day === cell.date }"
                :title="`${cell.used_min} min réservées sur ${cell.open_min} ouvertes`"
                :aria-pressed="day === cell.date"
                @click="selectDay(cell)"
            >
                <span>{{ dayNumber(cell.date) }}</span>
                <span class="month-bar" v-if="cell.open_min">
                    <span :style="{ width: Math.min(cell.load, 100) + '%' }"></span>
                </span>
                <span class="month-count" v-if="cell.slots_total">
                    {{ cell.slots_used }}/{{ cell.slots_total }}
                </span>
                <span class="month-dots" v-if="dotsFor(cell.date).length">
                    <span v-for="couleur in dotsFor(cell.date)" :key="couleur"
                          :style="{ background: couleur }"></span>
                </span>
            </button>
        </div>

        <!-- Vues jour et semaine : planning à l'heure. Les plages ouvertes
             forment le fond, les rendez-vous se posent dessus. -->
        <div class="timeline" v-else-if="hasTimeline">
            <div class="tl-head">
                <span class="tl-corner"></span>
                <button
                    v-for="jour in timelineDays"
                    :key="jour.date"
                    type="button"
                    class="tl-day"
                    :class="{ 'is-closed': !jour.open_min }"
                    @click="openDay(jour.date)"
                >
                    <span class="tl-day-name">{{ dayName(jour.date) }}</span>
                    <span class="tl-day-num">{{ dayNumber(jour.date) }}</span>
                    <span class="tl-day-count">
                        {{ jour.slots_total ? `${jour.slots_used}/${jour.slots_total}` : 'Fermé' }}
                    </span>
                </button>
            </div>

            <div class="tl-grid">
                <div class="tl-gutter">
                    <span v-for="m in hourMarks" :key="m" :style="{ top: topPct(m) + '%' }">
                        {{ hourLabel(m) }}
                    </span>
                </div>

                <div class="tl-cols" ref="cols">
                    <span v-for="m in hourMarks" :key="`line${m}`" class="tl-line"
                          :style="{ top: topPct(m) + '%' }"></span>

                    <div class="tl-col" v-for="jour in timelineDays" :key="jour.date">
                        <span v-for="(bande, i) in closedBands(jour)" :key="`off${i}`"
                              class="tl-closed" :style="rangeStyle(bande)"></span>

                        <button
                            v-for="a in jour.evenements"
                            :key="a.id"
                            type="button"
                            class="tl-event"
                            :class="eventClass(a)"
                            :style="eventStyle(a)"
                            :title="`${a.client} — ${a.service} · ${statusLabel(a.status)}`"
                            @click="focusAppointment(a.id)"
                        >
                            <span class="tl-ico"><Icon :name="statusIcon(a.status)" :size="12" /></span>
                            <span class="tl-line1">
                                <span class="tl-event-time">{{ hourLabel(a.start_min) }}</span>
                                <span class="tl-event-name">{{ a.client }}</span>
                            </span>
                            <span class="tl-event-serv">{{ a.service }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <p class="empty-state" v-if="!appointments.length">Aucun rendez-vous sur cette période.</p>
        </div>

        <!-- Ni plage ouverte ni rendez-vous : afficher des heures donnerait à
             croire que le salon est ouvert. -->
        <div class="empty-state" v-else>
            <p style="margin: 0 0 14px">
                {{ isDay
                    ? 'Aucune disponibilité définie pour cette journée.'
                    : 'Aucune disponibilité définie sur cette semaine.' }}
            </p>
            <Link :href="route('admin.availability.index')" class="btn btn--ghost btn--sm">
                Définir les disponibilités
            </Link>
        </div>
    </section>

    <section class="card admin-card">
        <!-- Filtres. Chacun conserve les autres : chercher un client ne doit
             pas effacer le filtre de statut. -->

        <div class="row-list" v-if="appointments.length">
            <div
                class="row-item"
                v-for="a in appointments"
                :key="a.id"
                :ref="'appointment' + a.id"
                :class="{ 'is-focused': focus && focus.id === a.id }"
            >
                <div class="row-main">
                    <p class="row-title">
                        {{ formatDateTime(a.starts_at) }} — {{ a.client }}
                        <span v-if="a.is_late_night" class="badge badge--yellow" style="margin-left: 8px">Tarif ×2</span>
                    </p>
                    <p class="row-meta">
                        {{ a.service }} · {{ a.duration_min }} min · {{ money(a.total_due) }}
                        <span v-if="a.debt > 0" style="color: var(--danger)"> · dette {{ money(a.debt) }}</span>
                    </p>
                    <p class="row-meta" v-if="a.comment">« {{ a.comment }} »</p>
                    <p class="row-meta" v-if="a.proposals.length">
                        Créneaux proposés :
                        <span v-for="p in a.proposals" :key="p.id">{{ formatDateTime(p.starts_at) }} </span>
                    </p>
                </div>

                <span class="badge" :class="statusClass(a.status)">{{ statusLabel(a.status) }}</span>

                <div class="row-actions">
                    <button v-if="a.status === 'pending'" type="button" class="btn btn--solid btn--sm"
                            @click="confirm(a.id)">Confirmer</button>
                    <button v-if="a.status === 'pending'" type="button" class="btn btn--ghost btn--sm"
                            @click="openCounter(a.id)">Autre créneau</button>
                    <button v-if="a.status === 'pending'" type="button" class="btn btn--ghost btn--sm"
                            @click="openRefuse(a.id)">Refuser</button>
                    <button v-if="['confirmed', 'counter_accepted'].includes(a.status)"
                            type="button" class="btn btn--solid btn--sm" @click="complete(a.id)">Réalisé</button>

                    <!-- Deux issues distinctes quand la prestation n'a pas eu
                         lieu : le client n'est pas venu, ou le studio annule. -->
                    <button v-if="['confirmed', 'counter_accepted'].includes(a.status)"
                            type="button" class="btn btn--ghost btn--sm btn--danger"
                            @click="noShow(a)">Non venu</button>

                    <button v-if="['pending', 'confirmed', 'counter_proposed', 'counter_accepted'].includes(a.status)"
                            type="button" class="btn btn--ghost btn--sm"
                            @click="openCancel(a.id)">Annuler</button>
                </div>

                <!-- Contre-proposition : de 1 à 3 créneaux, choisis parmi les
                     disponibilités réelles pour cette prestation. -->
                <div v-if="counterFor === a.id" class="counter-panel">
                    <p class="field-help" v-if="counterLoading">Chargement des créneaux…</p>

                    <template v-else-if="counterDays.length">
                        <p class="field-help">
                            Sélectionnez jusqu'à trois créneaux ({{ counterPicked.length }}/3).
                            Seules les disponibilités réelles pour cette prestation sont proposées.
                        </p>

                        <div class="counter-days">
                            <button
                                v-for="d in counterDays"
                                :key="d"
                                type="button"
                                class="badge"
                                :class="counterDay === d ? 'badge--yellow' : 'badge--green'"
                                style="border: none"
                                @click="counterDay = d"
                            >{{ formatDateLong(d) }}</button>
                        </div>

                        <div class="counter-slots" v-if="counterDay">
                            <button
                                v-for="t in counterSlots[counterDay]"
                                :key="t"
                                type="button"
                                class="btn btn--ghost btn--sm"
                                :class="{ 'is-picked': isPicked(counterDay, t) }"
                                @click="togglePick(counterDay, t)"
                            >{{ t }}</button>
                        </div>

                        <ul class="counter-picked" v-if="counterPicked.length">
                            <li v-for="p in counterPicked" :key="p">{{ formatDateTime(p.replace(' ', 'T')) }}</li>
                        </ul>

                        <label class="field" style="margin-top: 14px">
                            <span class="field-label">Message</span>
                            <input type="text" v-model="counterNote" placeholder="Optionnel">
                        </label>

                        <div class="row-actions">
                            <button type="button" class="btn btn--solid btn--sm"
                                    :disabled="!counterPicked.length" @click="submitCounter">
                                Envoyer {{ counterPicked.length ? `(${counterPicked.length})` : '' }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm" @click="counterFor = null">Annuler</button>
                        </div>
                    </template>

                    <template v-else>
                        <p class="empty-state">
                            Aucun créneau libre pour cette prestation dans les prochaines semaines.
                            Ouvrez des disponibilités avant de proposer une alternative.
                        </p>
                        <button type="button" class="btn btn--ghost btn--sm" @click="counterFor = null">Fermer</button>
                    </template>
                </div>

                <div v-if="cancelFor === a.id" class="counter-panel">
                    <p class="field-help">
                        Le client est prévenu par email. Cette annulation vient du studio :
                        elle ne compte pas contre lui.
                    </p>
                    <label class="field">
                        <span class="field-label">Motif (facultatif)</span>
                        <input type="text" v-model="cancelForm.reason" placeholder="Communiqué au client">
                    </label>
                    <div class="row-actions">
                        <button type="button" class="btn btn--solid btn--sm" @click="submitCancel">Confirmer l'annulation</button>
                        <button type="button" class="btn btn--ghost btn--sm" @click="cancelFor = null">Fermer</button>
                    </div>
                </div>

                <div v-if="refuseFor === a.id" style="flex: 1 1 100%; border-top: 1px solid var(--border); padding-top: 14px">
                    <label class="field">
                        <span class="field-label">Motif du refus</span>
                        <input type="text" v-model="refuseForm.reason" placeholder="Communiqué au client">
                    </label>
                    <div class="row-actions">
                        <button type="button" class="btn btn--solid btn--sm" @click="submitRefuse">Refuser</button>
                        <button type="button" class="btn btn--ghost btn--sm" @click="refuseFor = null">Annuler</button>
                    </div>
                </div>
            </div>
        </div>

        <p v-else class="empty-state">
            {{ hasFilters ? 'Aucun rendez-vous ne correspond à ces filtres.' : 'Aucun rendez-vous ce mois-ci.' }}
        </p>
    </section>
</template>
