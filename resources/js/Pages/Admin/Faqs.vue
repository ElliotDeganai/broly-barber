<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { router, useForm } from '@inertiajs/vue3'

export default {
    layout: AdminLayout,
    components: { Icon },

    props: { faqs: { type: Array, default: () => [] } },

    data() {
        return {
            createForm: useForm({ question: '', answer: '', is_published: true }),
            editing: null,
            editForm: useForm({ question: '', answer: '', is_published: true, position: 0 }),
        }
    },

    methods: {
        create() {
            this.createForm.post(route('admin.faqs.store'), {
                preserveScroll: true,
                onSuccess: () => this.createForm.reset(),
            })
        },

        edit(faq) {
            this.editing = faq.id
            this.editForm.question = faq.question
            this.editForm.answer = faq.answer
            this.editForm.is_published = faq.is_published
            this.editForm.position = faq.position
        },

        save(faq) {
            this.editForm.put(route('admin.faqs.update', faq.id), {
                preserveScroll: true,
                onSuccess: () => { this.editing = null },
            })
        },

        remove(faq) {
            if (confirm('Supprimer cette question ?')) {
                router.delete(route('admin.faqs.destroy', faq.id), { preserveScroll: true })
            }
        },
    },
}
</script>

<template>
    <h1 class="admin-title">FAQ</h1>
    <p class="admin-sub">Les questions publiées apparaissent sur la page FAQ, dans cet ordre.</p>

    <section class="card admin-card">
        <h2 class="admin-card-title">Nouvelle question</h2>

        <label class="field">
            <span class="field-label">Question</span>
            <input type="text" v-model="createForm.question">
        </label>
        <label class="field">
            <span class="field-label">Réponse</span>
            <textarea rows="4" v-model="createForm.answer"></textarea>
        </label>

        <button type="button" class="btn btn--solid btn--sm" @click="create">
            <Icon name="plus" :size="16" /> Ajouter
        </button>
    </section>

    <section class="card admin-card">
        <div class="row-list" v-if="faqs.length">
            <div class="row-item" v-for="faq in faqs" :key="faq.id">
                <div class="row-main" v-if="editing !== faq.id">
                    <p class="row-title">{{ faq.question }}</p>
                    <p class="row-meta">{{ faq.answer }}</p>
                </div>

                <div class="row-main" v-else>
                    <input type="text" v-model="editForm.question" style="margin-bottom: 8px">
                    <textarea rows="4" v-model="editForm.answer" style="margin-bottom: 8px"></textarea>
                    <label class="check">
                        <input type="checkbox" v-model="editForm.is_published">
                        <span>Publiée</span>
                    </label>
                </div>

                <span class="badge" :class="faq.is_published ? 'badge--green' : 'badge--danger'"
                      v-if="editing !== faq.id">
                    {{ faq.is_published ? 'Publiée' : 'Masquée' }}
                </span>

                <div class="row-actions">
                    <template v-if="editing === faq.id">
                        <button type="button" class="btn btn--solid btn--sm" @click="save(faq)">Enregistrer</button>
                        <button type="button" class="btn btn--ghost btn--sm" @click="editing = null">Annuler</button>
                    </template>
                    <template v-else>
                        <button type="button" class="btn-icon" aria-label="Modifier" @click="edit(faq)">
                            <Icon name="pencil" :size="16" />
                        </button>
                        <button type="button" class="btn-icon btn-icon--danger" aria-label="Supprimer"
                                @click="remove(faq)">
                            <Icon name="trash" :size="16" />
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <p v-else class="empty-state">Aucune question.</p>
    </section>
</template>
