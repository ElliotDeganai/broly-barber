<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { router, useForm } from '@inertiajs/vue3'

/** Galerie : les photos publiées alimentent le carrousel public. */
export default {
    layout: AdminLayout,
    components: { Icon },

    props: {
        items:    { type: Array, default: () => [] },
        services: { type: Array, default: () => [] },
    },

    data() {
        return {
            form: useForm({ images: [], service_id: '', alt: '' }),
            previews: [],
            editing: null,
            editForm: useForm({ alt: '', service_id: '', is_published: true }),
        }
    },

    beforeUnmount() {
        this.previews.forEach((p) => URL.revokeObjectURL(p.url))
    },

    methods: {
        pickFiles(event) {
            this.previews.forEach((p) => URL.revokeObjectURL(p.url))

            this.form.images = Array.from(event.target.files)
            this.previews = this.form.images.map((file) => ({
                name: file.name,
                url: URL.createObjectURL(file),
            }))
        },

        upload() {
            this.form.post(route('admin.gallery.store'), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    this.form.reset()
                    this.previews = []
                    if (this.$refs.fileInput) this.$refs.fileInput.value = ''
                },
            })
        },

        edit(item) {
            this.editing = item.id
            this.editForm.alt = item.alt || ''
            this.editForm.service_id = item.service_id || ''
            this.editForm.is_published = item.is_published
        },

        saveEdit(item) {
            this.editForm.transform((d) => ({ ...d, _method: 'put' }))
                .post(route('admin.gallery.update', item.id), {
                    preserveScroll: true,
                    onSuccess: () => { this.editing = null },
                })
        },

        togglePublish(item) {
            router.put(route('admin.gallery.update', item.id), {
                alt: item.alt || '',
                service_id: item.service_id || '',
                is_published: !item.is_published,
            }, { preserveScroll: true })
        },

        remove(item) {
            if (confirm('Supprimer définitivement cette photo ?')) {
                router.delete(route('admin.gallery.destroy', item.id), { preserveScroll: true })
            }
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Galerie</h1>
    <p class="admin-sub">
        Les photos publiées apparaissent dans le carrousel de la page Galerie.
    </p>

    <section class="card admin-card">
        <h2 class="admin-card-title">Ajouter des photos</h2>

        <div class="field">
            <span class="field-label">Fichiers</span>
            <p class="field-help">Sélection multiple possible, jusqu'à vingt à la fois.</p>
            <input ref="fileInput" type="file" multiple accept="image/*" @change="pickFiles">
        </div>

        <div class="thumb-grid" v-if="previews.length" style="margin-bottom: 16px">
            <div class="thumb" v-for="preview in previews" :key="preview.name">
                <img :src="preview.url" :alt="preview.name">
            </div>
        </div>

        <div class="field-row">
            <label class="field">
                <span class="field-label">Prestation associée</span>
                <p class="field-help">Permet de filtrer la galerie publique.</p>
                <select v-model="form.service_id">
                    <option value="">Aucune</option>
                    <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Description</span>
                <p class="field-help">Décrit la photo pour ceux qui ne la voient pas.</p>
                <input type="text" v-model="form.alt">
            </label>
        </div>

        <button type="button" class="btn btn--solid btn--sm"
                :disabled="form.processing || !form.images.length" @click="upload">
            {{ form.processing ? `Envoi ${form.progress ? form.progress.percentage + ' %' : '…'}` : 'Téléverser' }}
        </button>
    </section>

    <section class="card admin-card" v-if="items.length">
        <h2 class="admin-card-title">Photos</h2>

        <div class="thumb-grid">
            <figure class="thumb" v-for="item in items" :key="item.id"
                    :class="{ 'is-hidden': !item.is_published }" style="margin: 0">
                <img :src="item.url" :alt="item.alt || ''" loading="lazy" decoding="async">

                <div class="thumb-tools">
                    <button type="button" class="btn-icon"
                            :aria-label="item.is_published ? 'Masquer' : 'Publier'"
                            @click="togglePublish(item)">
                        <Icon :name="item.is_published ? 'check' : 'alert'" :size="15" />
                    </button>
                    <button type="button" class="btn-icon" aria-label="Modifier" @click="edit(item)">
                        <Icon name="pencil" :size="15" />
                    </button>
                    <button type="button" class="btn-icon btn-icon--danger" aria-label="Supprimer"
                            @click="remove(item)">
                        <Icon name="trash" :size="15" />
                    </button>
                </div>

                <div v-if="editing === item.id" style="padding: 10px">
                    <input type="text" v-model="editForm.alt" placeholder="Description" style="margin-bottom: 8px">
                    <select v-model="editForm.service_id" style="margin-bottom: 8px">
                        <option value="">Aucune prestation</option>
                        <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div class="row-actions">
                        <button type="button" class="btn btn--solid btn--sm" @click="saveEdit(item)">OK</button>
                        <button type="button" class="btn btn--ghost btn--sm" @click="editing = null">Annuler</button>
                    </div>
                </div>
            </figure>
        </div>
    </section>

    <p v-else class="empty-state">Aucune photo dans la galerie.</p>
</template>
