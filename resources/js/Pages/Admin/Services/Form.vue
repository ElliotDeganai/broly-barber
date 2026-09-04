<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { Link, useForm } from '@inertiajs/vue3'

export default {
    layout: AdminLayout,
    components: { Icon, Link },

    props: { service: { type: Object, default: null } },

    data() {
        return {
            form: useForm({
                name: this.service?.name || '',
                includes: this.service?.includes || '',
                description: this.service?.description || '',
                has_restructuration: this.service?.has_restructuration ?? true,
                price: this.service?.price || '',
                duration_min: this.service?.duration_min || 30,
                is_active: this.service?.is_active ?? true,
                position: this.service?.position ?? 0,
                image: null,
            }),
            preview: null,
        }
    },

    computed: {
        isEdit() {
            return Boolean(this.service)
        },
    },

    beforeUnmount() {
        // Une URL d'objet occupe de la mémoire tant qu'on ne la libère pas
        if (this.preview) URL.revokeObjectURL(this.preview)
    },

    methods: {
        pickImage(event) {
            if (this.preview) URL.revokeObjectURL(this.preview)

            const file = event.target.files[0]
            this.form.image = file || null
            this.preview = file ? URL.createObjectURL(file) : null
        },

        submit() {
            const options = { forceFormData: true, preserveScroll: true }

            this.isEdit
                // Inertia n'envoie pas de fichier en PUT : on passe par POST
                ? this.form.transform((d) => ({ ...d, _method: 'put' }))
                      .post(route('admin.services.update', this.service.id), options)
                : this.form.post(route('admin.services.store'), options)
        },
    },
}
</script>

<template>
    <h1 class="admin-title">{{ isEdit ? 'Modifier la prestation' : 'Nouvelle prestation' }}</h1>
    <p class="admin-sub">
        La description est lue par le client avant de réserver : elle doit dire
        clairement ce qui est compris.
    </p>

    <section class="card admin-card">
        <div class="field-row">
            <label class="field">
                <span class="field-label">Nom</span>
                <input type="text" v-model="form.name">
                <span class="field-error" v-if="form.errors.name">{{ form.errors.name }}</span>
            </label>

            <label class="field">
                <span class="field-label">Contenu</span>
                <p class="field-help">Ex : « Ciseau + Tondeuse ». Affiché entre parenthèses.</p>
                <input type="text" v-model="form.includes">
            </label>

            <label class="field">
                <span class="field-label">Tarif (€)</span>
                <input type="number" step="0.01" min="0" v-model="form.price">
                <span class="field-error" v-if="form.errors.price">{{ form.errors.price }}</span>
            </label>

            <label class="field">
                <span class="field-label">Durée (minutes)</span>
                <p class="field-help">Pilote le calcul des créneaux disponibles.</p>
                <input type="number" min="5" max="480" step="5" v-model="form.duration_min">
                <span class="field-error" v-if="form.errors.duration_min">{{ form.errors.duration_min }}</span>
            </label>
        </div>

        <label class="field">
            <span class="field-label">Description</span>
            <p class="field-help">Ce qui est compris, ce qui ne l'est pas.</p>
            <textarea rows="4" v-model="form.description"></textarea>
        </label>

        <label class="check check--spaced">
            <input type="checkbox" v-model="form.has_restructuration">
            <span>Restructuration comprise</span>
        </label>

        <label class="check check--spaced">
            <input type="checkbox" v-model="form.is_active">
            <span>Prestation visible sur le site</span>
        </label>

        <div class="field">
            <span class="field-label">Visuel</span>
            <p class="field-help">Format portrait. Les images trop grandes sont redimensionnées.</p>

            <div class="thumb" style="max-width: 180px; margin-bottom: 10px"
                 v-if="preview || service?.image">
                <img :src="preview || service.image" alt="">
            </div>

            <input type="file" accept="image/*" @change="pickImage">
        </div>

        <div class="row-actions">
            <button type="button" class="btn btn--solid btn--sm" :disabled="form.processing" @click="submit">
                {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
            <Link :href="route('admin.services.index')" class="btn btn--ghost btn--sm">Retour</Link>
        </div>
    </section>
</template>
