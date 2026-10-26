<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { Link, useForm } from '@inertiajs/vue3'

export default {
    layout: AdminLayout,
    components: { Icon, Link },

    props: {
        service: { type: Object, default: null },
        palette: { type: Array,  default: () => [] },
    },

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
                color: this.service?.color || '',
                position: this.service?.position ?? 0,
                image: null,
                image_before: null,
            }),
            preview: null,
            previewBefore: null,
        }
    },

    computed: {
        isEdit() {
            return Boolean(this.service)
        },

        /** Couleur montrée dans l'aperçu : celle choisie, sinon celle de repli. */
        shownColor() {
            return this.form.color || this.service?.display_color || this.palette[0] || '#69E64B'
        },
    },

    beforeUnmount() {
        // Une URL d'objet occupe de la mémoire tant qu'on ne la libère pas
        if (this.preview) URL.revokeObjectURL(this.preview)
        if (this.previewBefore) URL.revokeObjectURL(this.previewBefore)
    },

    methods: {
        /** Un second clic sur la pastille active rend la couleur automatique. */
        pickColor(color) {
            this.form.color = this.form.color === color ? '' : color
        },

        /** Les deux visuels suivent le même chemin : un seul gestionnaire. */
        pickImage(event, champ = 'image') {
            const apercu = champ === 'image' ? 'preview' : 'previewBefore'

            if (this[apercu]) URL.revokeObjectURL(this[apercu])

            const file = event.target.files[0]
            this.form[champ] = file || null
            this[apercu] = file ? URL.createObjectURL(file) : null
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

        <div class="field">
            <span class="field-label">Couleur dans le planning</span>
            <p class="field-help">
                Repère la prestation dans le calendrier des rendez-vous. Sans choix,
                une couleur est attribuée automatiquement.
            </p>

            <div class="swatches">
                <button
                    v-for="color in palette"
                    :key="color"
                    type="button"
                    class="swatch"
                    :class="{ 'is-active': form.color === color }"
                    :style="{ background: color }"
                    :aria-label="`Couleur ${color}`"
                    :aria-pressed="form.color === color"
                    @click="pickColor(color)"
                ></button>

                <label class="swatch swatch--custom" :style="{ background: shownColor }">
                    <input type="color" v-model="form.color">
                </label>

                <button
                    v-if="form.color"
                    type="button"
                    class="btn btn--ghost btn--sm"
                    @click="form.color = ''"
                >Automatique</button>
            </div>

            <span class="field-error" v-if="form.errors.color">{{ form.errors.color }}</span>
        </div>

        <label class="check field">
            <input type="checkbox" v-model="form.has_restructuration">
            <span>Restructuration comprise</span>
        </label>

        <label class="check field">
            <input type="checkbox" v-model="form.is_active">
            <span>Prestation visible sur le site</span>
        </label>

        <div class="field">
            <span class="field-label">Visuels avant / après</span>
            <p class="field-help">
                Format portrait. Les images trop grandes sont redimensionnées automatiquement.
                L'animation ne se déclenche que si les deux sont renseignées ; avec le seul
                visuel « après », la prestation affiche une image fixe.
            </p>

            <div class="visuels">
                <div>
                    <span class="visuel-titre">Avant <em>— facultatif</em></span>
                    <div class="thumb" style="margin-bottom: 10px"
                         v-if="previewBefore || service?.before">
                        <img :src="previewBefore || service.before" alt="">
                    </div>
                    <input type="file" accept="image/*" @change="pickImage($event, 'image_before')">
                    <span class="field-error" v-if="form.errors.image_before">{{ form.errors.image_before }}</span>
                </div>

                <div>
                    <span class="visuel-titre">Après <em>— visuel principal</em></span>
                    <div class="thumb" style="margin-bottom: 10px"
                         v-if="preview || service?.image">
                        <img :src="preview || service.image" alt="">
                    </div>
                    <input type="file" accept="image/*" @change="pickImage($event, 'image')">
                    <span class="field-error" v-if="form.errors.image">{{ form.errors.image }}</span>
                </div>
            </div>
        </div>

        <div class="row-actions">
            <button type="button" class="btn btn--solid btn--sm" :disabled="form.processing" @click="submit">
                {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
            <Link :href="route('admin.services.index')" class="btn btn--ghost btn--sm">Retour</Link>
        </div>
    </section>
</template>
