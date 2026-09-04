<script>
import Icon from '@/Components/Icon.vue'

/**
 * Éditeur de texte enrichi.
 *
 * Repose sur `contenteditable` et `document.execCommand`. Cette API est
 * marquée obsolète mais reste implémentée par tous les navigateurs, et elle
 * évite d'embarquer une bibliothèque de 200 Ko pour cinq pages de contenu.
 *
 * Deux garde-fous compensent ses défauts : le collage est converti en texte
 * brut, et le serveur filtre le HTML reçu selon une liste blanche.
 */
export default {
    components: { Icon },

    props: {
        modelValue: { type: String, default: '' },
    },

    emits: ['update:modelValue'],

    data() {
        return {
            linkOpen: false,
            linkUrl: '',
            // Mémorise la sélection : ouvrir le champ d'URL la ferait perdre
            savedRange: null,
            // Commandes actives à l'emplacement du curseur
            active: {},
            currentBlock: 'p',
        }
    },

    computed: {
        /**
         * Outils groupés : les séparateurs évitent une rangée indistincte de
         * quinze boutons où l'on ne retrouve rien.
         */
        groups() {
            return [
                [
                    { cmd: 'undo', icon: 'undo', label: 'Annuler' },
                    { cmd: 'redo', icon: 'redo', label: 'Rétablir' },
                ],
                [
                    { block: 'p',  icon: 'paragraph', label: 'Paragraphe' },
                    { block: 'h2', icon: 'h2',        label: 'Titre' },
                    { block: 'h3', icon: 'h3',        label: 'Sous-titre' },
                ],
                [
                    { cmd: 'bold',          icon: 'bold',      label: 'Gras',      state: 'bold' },
                    { cmd: 'italic',        icon: 'italic',    label: 'Italique',  state: 'italic' },
                    { cmd: 'underline',     icon: 'underline', label: 'Souligné',  state: 'underline' },
                    { cmd: 'strikeThrough', icon: 'strike',    label: 'Barré',     state: 'strikeThrough' },
                ],
                [
                    { cmd: 'insertUnorderedList', icon: 'list',   label: 'Liste à puces',    state: 'insertUnorderedList' },
                    { cmd: 'insertOrderedList',   icon: 'listOl', label: 'Liste numérotée',  state: 'insertOrderedList' },
                ],
                [
                    { block: 'blockquote', icon: 'quote', label: 'Citation' },
                    { rule: true,          icon: 'minus', label: 'Trait de séparation' },
                ],
                [
                    { link: true,   icon: 'link',   label: 'Insérer un lien' },
                    { unlink: true, icon: 'unlink', label: 'Retirer le lien' },
                ],
                [
                    { cmd: 'removeFormat', icon: 'eraser', label: 'Effacer la mise en forme' },
                ],
            ]
        },
    },

    watch: {
        // Mise à jour venue de l'extérieur : on ne réécrit le contenu que si
        // la valeur diffère, sinon le curseur sauterait à chaque frappe.
        modelValue(value) {
            if (this.$refs.editor && this.$refs.editor.innerHTML !== value) {
                this.$refs.editor.innerHTML = value || ''
            }
        },
    },

    mounted() {
        this.$refs.editor.innerHTML = this.modelValue || ''

        // Sans cette instruction, la touche Entrée crée un <div> sous Chrome
        // et un <p> sous Firefox. On uniformise sur le paragraphe, qui est la
        // balise attendue par le rendu du site.
        try {
            document.execCommand('defaultParagraphSeparator', false, 'p')
        } catch {
            // Navigateur qui refuse la commande : le filtre serveur convertit
            // de toute façon les <div> en paragraphes.
        }
    },

    methods: {
        emit() {
            this.$emit('update:modelValue', this.$refs.editor.innerHTML)
        },

        /** Met en évidence les outils actifs là où se trouve le curseur. */
        syncState() {
            const active = {}

            for (const group of this.groups) {
                for (const tool of group) {
                    if (!tool.state) continue

                    try {
                        active[tool.state] = document.queryCommandState(tool.state)
                    } catch {
                        // Certaines commandes lèvent une exception hors focus
                        active[tool.state] = false
                    }
                }
            }

            this.active = active

            try {
                this.currentBlock = (document.queryCommandValue('formatBlock') || 'p').toLowerCase()
            } catch {
                this.currentBlock = 'p'
            }
        },

        isActive(tool) {
            if (tool.block) return this.currentBlock === tool.block

            return Boolean(tool.state && this.active[tool.state])
        },

        run(tool) {
            this.$refs.editor.focus()

            if (tool.link) {
                this.openLink()
                return
            }

            if (tool.unlink) {
                document.execCommand('unlink', false, null)
                this.emit()
                this.syncState()

                return
            }

            if (tool.rule) {
                document.execCommand('insertHorizontalRule', false, null)
                this.emit()

                return
            }

            if (tool.block) {
                // Un second clic sur le même bloc revient au paragraphe
                const current = document.queryCommandValue('formatBlock').toLowerCase()
                document.execCommand('formatBlock', false, current === tool.block ? 'p' : tool.block)
            } else {
                document.execCommand(tool.cmd, false, null)
            }

            this.emit()
            this.syncState()
        },

        openLink() {
            const selection = window.getSelection()

            if (!selection.rangeCount || selection.isCollapsed) {
                alert("Sélectionnez d'abord le texte à transformer en lien.")
                return
            }

            this.savedRange = selection.getRangeAt(0).cloneRange()
            this.linkUrl = ''
            this.linkOpen = true

            this.$nextTick(() => this.$refs.linkInput?.focus())
        },

        applyLink() {
            if (!this.linkUrl || !this.savedRange) {
                this.linkOpen = false
                return
            }

            const selection = window.getSelection()
            selection.removeAllRanges()
            selection.addRange(this.savedRange)

            document.execCommand('createLink', false, this.linkUrl)

            this.linkOpen = false
            this.savedRange = null
            this.emit()
        },

        /**
         * Collage en texte brut.
         *
         * Un copier-coller depuis Word ou une page web amène des dizaines de
         * balises et de styles en ligne. Le serveur les filtrerait, mais
         * l'éditeur afficherait entre-temps un contenu qui ne sera pas conservé.
         */
        onPaste(event) {
            event.preventDefault()

            const text = (event.clipboardData || window.clipboardData).getData('text/plain')
            document.execCommand('insertText', false, text)
        },
    },
}
</script>

<template>
    <div class="rte">
        <div class="rte-toolbar">
            <template v-for="(group, g) in groups" :key="g">
                <span v-if="g > 0" class="rte-sep" aria-hidden="true"></span>

                <button
                    v-for="tool in group"
                    :key="tool.label"
                    type="button"
                    class="rte-tool"
                    :class="{ 'is-active': isActive(tool) }"
                    :title="tool.label"
                    :aria-label="tool.label"
                    :aria-pressed="isActive(tool)"
                    @click="run(tool)"
                >
                    <Icon :name="tool.icon" :size="16" />
                </button>
            </template>
        </div>

        <div
            ref="editor"
            class="rte-editor prose"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            @input="emit"
            @blur="emit"
            @paste="onPaste"
            @keyup="syncState"
            @mouseup="syncState"
            @focus="syncState"
        ></div>

        <div v-if="linkOpen" class="rte-link">
            <input
                ref="linkInput"
                type="url"
                v-model="linkUrl"
                placeholder="https://exemple.com"
                @keyup.enter="applyLink"
                @keyup.esc="linkOpen = false"
            >
            <button type="button" class="btn btn--solid btn--sm" @click="applyLink">Ajouter</button>
            <button type="button" class="btn btn--ghost btn--sm" @click="linkOpen = false">Annuler</button>
        </div>
    </div>
</template>
