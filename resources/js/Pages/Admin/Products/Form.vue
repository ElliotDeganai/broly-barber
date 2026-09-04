<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

export default {
    layout: AdminLayout,
    components: { Link },

    props: {
        product:    { type: Object, default: null },
        categories: { type: Array,  default: () => [] },
    },

    data() {
        return {
            form: useForm({
                category_id: this.product?.category_id || '',
                name: this.product?.name || '',
                description: this.product?.description || '',
                price: this.product?.price || '',
                vat_rate: this.product?.vat_rate || 20,
                is_published: this.product?.is_published ?? true,
                position: this.product?.position ?? 0,
                photo: null,
            }),
            preview: null,
        }
    },

    computed: {
        isEdit() {
            return Boolean(this.product)
        },
    },

    beforeUnmount() {
        if (this.preview) URL.revokeObjectURL(this.preview)
    },

    methods: {
        pickPhoto(event) {
            if (this.preview) URL.revokeObjectURL(this.preview)

            const file = event.target.files[0]
            this.form.photo = file || null
            this.preview = file ? URL.createObjectURL(file) : null
        },

        submit() {
            const options = { forceFormData: true, preserveScroll: true }

            this.isEdit
                ? this.form.transform((d) => ({ ...d, _method: 'put' }))
                      .post(route('admin.products.update', this.product.id), options)
                : this.form.post(route('admin.products.store'), options)
        },
    },
}
</script>

<template>
    <h1 class="admin-title">{{ isEdit ? 'Modifier le produit' : 'Nouveau produit' }}</h1>

    <section class="card admin-card">
        <div class="field-row">
            <label class="field">
                <span class="field-label">Nom</span>
                <input type="text" v-model="form.name">
                <span class="field-error" v-if="form.errors.name">{{ form.errors.name }}</span>
            </label>

            <label class="field">
                <span class="field-label">Gamme</span>
                <select v-model="form.category_id">
                    <option value="">Aucune</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Prix TTC (€)</span>
                <input type="number" step="0.01" min="0" v-model="form.price">
                <span class="field-error" v-if="form.errors.price">{{ form.errors.price }}</span>
            </label>

            <label class="field">
                <span class="field-label">TVA (%)</span>
                <p class="field-help">Sert au calcul du chiffre d'affaires hors taxes.</p>
                <input type="number" step="0.01" min="0" max="100" v-model="form.vat_rate">
            </label>
        </div>

        <label class="field">
            <span class="field-label">Description</span>
            <textarea rows="3" v-model="form.description"></textarea>
        </label>

        <label class="check check--spaced">
            <input type="checkbox" v-model="form.is_published">
            <span>Visible sur le site</span>
        </label>

        <div class="field">
            <span class="field-label">Photo</span>

            <div class="thumb" style="max-width: 160px; margin-bottom: 10px"
                 v-if="preview || product?.photo">
                <img :src="preview || product.photo" alt="">
            </div>

            <input type="file" accept="image/*" @change="pickPhoto">
        </div>

        <div class="row-actions">
            <button type="button" class="btn btn--solid btn--sm" :disabled="form.processing" @click="submit">
                {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
            <Link :href="route('admin.products.index')" class="btn btn--ghost btn--sm">Retour</Link>
        </div>
    </section>
</template>
