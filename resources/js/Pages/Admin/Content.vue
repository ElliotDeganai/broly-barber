<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import RichText from '@/Components/RichText.vue'
import { router, useForm } from '@inertiajs/vue3'

/**
 * Édition du contenu et des réglages.
 *
 * Une trentaine de champs à la file rendaient la page interminable. Ils sont
 * répartis en trois onglets, et à l'intérieur d'un onglet une seule section
 * s'affiche à la fois : on ne fait défiler que ce qu'on modifie.
 *
 * Les valeurs et les fichiers partent en deux tableaux distincts, `values` et
 * `images` : la notation par clé échouerait sur les underscores, que Laravel
 * peut lire comme des séparateurs de tableau imbriqué.
 */
export default {
    layout: AdminLayout,
    components: { Icon, RichText },

    props: {
        sections: { type: Array, default: () => [] },
        pages:    { type: Array, default: () => [] },
    },

    data() {
        const values = {}

        this.sections.forEach((section) => {
            section.fields.forEach((field) => {
                if (field.type !== 'image') values[field.key] = field.value ?? ''
            })
        })

        return {
            tab: 'content',
            activeSection: null,
            values,
            // Copie de référence : sert à savoir si quelque chose a changé
            initial: JSON.parse(JSON.stringify(values)),
            images: {},
            previews: {},
            saving: false,
            editingPage: null,
            pageForm: useForm({ title: '', body: '', is_published: true }),
        }
    },

    computed: {
        tabs() {
            return [
                { key: 'content',  label: 'Contenu du site', icon: 'pencil',   count: this.contentSections.length },
                { key: 'settings', label: 'Réglages',        icon: 'settings', count: this.settingSections.length },
                { key: 'pages',    label: 'Pages',           icon: 'help',     count: this.pages.length },
            ]
        },

        contentSections() {
            return this.sections.filter((s) => s.scope === 'content')
        },
        settingSections() {
            return this.sections.filter((s) => s.scope !== 'content')
        },

        /** Sections de l'onglet courant. */
        visibleSections() {
            return this.tab === 'settings' ? this.settingSections : this.contentSections
        },

        currentSection() {
            return this.visibleSections.find((s) => s.key === this.activeSection)
                || this.visibleSections[0]
                || null
        },

        /** Champs modifiés depuis le dernier enregistrement. */
        dirtyKeys() {
            return Object.keys(this.values).filter((k) => this.values[k] !== this.initial[k])
        },
        hasChanges() {
            return this.dirtyKeys.length > 0 || Object.keys(this.images).length > 0
        },

        /** Nombre de modifications en attente dans une section donnée. */
        sectionDirtyCount() {
            const map = {}

            this.sections.forEach((section) => {
                map[section.key] = section.fields.filter((f) =>
                    f.type === 'image'
                        ? this.images[f.key] !== undefined
                        : this.values[f.key] !== this.initial[f.key],
                ).length
            })

            return map
        },
    },

    watch: {
        // Changer d'onglet remet la sélection sur la première section
        tab() {
            this.activeSection = this.visibleSections[0]?.key || null
        },
    },

    mounted() {
        this.activeSection = this.contentSections[0]?.key || null

        // Prévient avant de quitter avec des modifications non enregistrées
        this._guard = (e) => {
            if (!this.hasChanges) return
            e.preventDefault()
            e.returnValue = ''
        }
        window.addEventListener('beforeunload', this._guard)
    },

    beforeUnmount() {
        window.removeEventListener('beforeunload', this._guard)
        Object.values(this.previews).forEach((url) => URL.revokeObjectURL(url))
    },

    methods: {
        pickImage(key, event) {
            const file = event.target.files[0]
            if (!file) return

            if (this.previews[key]) URL.revokeObjectURL(this.previews[key])

            this.images[key] = file
            this.previews[key] = URL.createObjectURL(file)
        },

        clearImage(key) {
            if (this.previews[key]) URL.revokeObjectURL(this.previews[key])

            delete this.images[key]
            delete this.previews[key]

            if (this.$refs['file_' + key]?.[0]) this.$refs['file_' + key][0].value = ''
        },

        save() {
            this.saving = true

            router.post(route('admin.content.update'), {
                values: this.values,
                images: this.images,
            }, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    // La référence se recale : plus rien n'est « en attente »
                    this.initial = JSON.parse(JSON.stringify(this.values))
                    Object.values(this.previews).forEach((url) => URL.revokeObjectURL(url))
                    this.images = {}
                    this.previews = {}
                },
                onFinish: () => { this.saving = false },
            })
        },

        editPage(page) {
            this.editingPage = page.id
            this.pageForm.title = page.title
            this.pageForm.body = page.body || ''
            this.pageForm.is_published = page.is_published
        },

        savePage(page) {
            this.pageForm.post(route('admin.pages.update', page.id), {
                preserveScroll: true,
                onSuccess: () => { this.editingPage = null },
            })
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Contenu du site</h1>
    <p class="admin-sub">
        Textes, visuels et réglages. Les modifications sont visibles sur le site
        dès l'enregistrement.
    </p>

    <!-- Onglets : contenu éditorial, paramètres de fonctionnement, pages -->
    <div class="cms-tabs">
        <button
            v-for="t in tabs"
            :key="t.key"
            type="button"
            class="cms-tab"
            :class="{ 'is-active': tab === t.key }"
            @click="tab = t.key"
        >
            <Icon :name="t.icon" :size="16" />
            <span>{{ t.label }}</span>
            <span class="cms-tab-count">{{ t.count }}</span>
        </button>
    </div>

    <!-- ─────────── Contenu et réglages ─────────── -->
    <div class="cms-layout" v-if="tab !== 'pages'">
        <!-- Sommaire des sections -->
        <nav class="cms-nav">
            <button
                v-for="section in visibleSections"
                :key="section.key"
                type="button"
                class="cms-nav-item"
                :class="{ 'is-active': currentSection && currentSection.key === section.key }"
                @click="activeSection = section.key"
            >
                <span>{{ section.label }}</span>
                <!-- Pastille : cette section a des modifications non enregistrées -->
                <span class="cms-nav-dot" v-if="sectionDirtyCount[section.key]">
                    {{ sectionDirtyCount[section.key] }}
                </span>
            </button>
        </nav>

        <section class="card admin-card cms-panel" v-if="currentSection">
            <h2 class="admin-card-title">{{ currentSection.label }}</h2>
            <p class="field-help" v-if="currentSection.help">{{ currentSection.help }}</p>

            <div class="field" v-for="field in currentSection.fields" :key="field.key">
                <span class="field-label">{{ field.label }}</span>
                <p class="field-help" v-if="field.help">{{ field.help }}</p>

                <template v-if="field.type === 'image'">
                    <div class="cms-image">
                        <div class="thumb" v-if="previews[field.key] || field.url">
                            <img :src="previews[field.key] || field.url" alt="">
                        </div>
                        <div class="thumb thumb--empty" v-else>
                            <Icon name="photo" :size="22" />
                        </div>

                        <div class="cms-image-actions">
                            <input :ref="'file_' + field.key" type="file" accept="image/*"
                                   @change="pickImage(field.key, $event)">
                            <button v-if="previews[field.key]" type="button" class="btn btn--ghost btn--sm"
                                    @click="clearImage(field.key)">Annuler</button>
                        </div>
                    </div>
                </template>

                <textarea v-else-if="field.type === 'textarea'" rows="3" v-model="values[field.key]"></textarea>
                <input v-else-if="field.type === 'number'" type="number" v-model="values[field.key]">
                <input v-else-if="field.type === 'url'" type="url" v-model="values[field.key]">
                <input v-else type="text" v-model="values[field.key]">
            </div>
        </section>
    </div>

    <!-- ─────────── Pages ─────────── -->
    <section class="card admin-card" v-else>
        <p class="field-help">
            « Qui suis-je », mentions légales, confidentialité, cookies et conditions
            d'annulation. Ces pages sont accessibles depuis le pied de page du site.
        </p>

        <div class="row-list">
            <div class="row-item" v-for="page in pages" :key="page.id">
                <div class="row-main" v-if="editingPage !== page.id">
                    <p class="row-title">{{ page.title }}</p>
                    <p class="row-meta">/p/{{ page.slug }}</p>
                </div>

                <div class="row-main" v-else>
                    <label class="field">
                        <span class="field-label">Titre</span>
                        <input type="text" v-model="pageForm.title">
                    </label>

                    <div class="field">
                        <span class="field-label">Contenu</span>
                        <p class="field-help">
                            Sélectionnez du texte puis cliquez sur un outil. Le collage
                            depuis un autre site est converti en texte simple.
                        </p>
                        <RichText v-model="pageForm.body" />
                    </div>

                    <label class="check">
                        <input type="checkbox" v-model="pageForm.is_published">
                        <span>Page visible sur le site</span>
                    </label>
                </div>

                <span class="badge" :class="page.is_published ? 'badge--green' : 'badge--danger'"
                      v-if="editingPage !== page.id">
                    {{ page.is_published ? 'Publiée' : 'Masquée' }}
                </span>

                <div class="row-actions">
                    <template v-if="editingPage === page.id">
                        <button type="button" class="btn btn--solid btn--sm" @click="savePage(page)">
                            Enregistrer
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm" @click="editingPage = null">
                            Annuler
                        </button>
                    </template>
                    <button v-else type="button" class="btn-icon" aria-label="Modifier" @click="editPage(page)">
                        <Icon name="pencil" :size="16" />
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Barre d'enregistrement : n'apparaît qu'en cas de modification -->
    <Transition name="toast">
        <div class="cms-savebar" v-if="tab !== 'pages' && hasChanges">
            <span class="cms-savebar-text">
                {{ dirtyKeys.length + Object.keys(images).length }} modification(s) non enregistrée(s)
            </span>
            <button type="button" class="btn btn--solid btn--sm" :disabled="saving" @click="save">
                {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
        </div>
    </Transition>
</template>
