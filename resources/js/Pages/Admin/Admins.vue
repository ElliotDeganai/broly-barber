<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { router, useForm } from '@inertiajs/vue3'

/** Comptes administrateurs : création, promotion, retrait des droits. */
export default {
    layout: AdminLayout,
    components: { Icon },

    props: {
        admins: { type: Array, required: true },
    },

    data() {
        return {
            // Un seul panneau ouvert à la fois : les deux formulaires font la
            // même chose vue du résultat, les afficher ensemble sèmerait le doute.
            panel: null,
            createForm: useForm({
                name: '', email: '', phone: '',
                password: '', password_confirmation: '',
                notify: true,
            }),
            promoteForm: useForm({
                email: '', password: '', password_confirmation: '', notify: true,
            }),
        }
    },

    computed: {
        /** Le dernier administrateur ne peut pas être retiré. */
        isLast() {
            return this.admins.length <= 1
        },
    },

    methods: {
        open(name) {
            this.panel = this.panel === name ? null : name
        },

        createAdmin() {
            this.createForm.post(route('admin.admins.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    this.createForm.reset()
                    this.panel = null
                },
            })
        },

        promoteAdmin() {
            this.promoteForm.post(route('admin.admins.promote'), {
                preserveScroll: true,
                onSuccess: () => {
                    this.promoteForm.reset()
                    this.panel = null
                },
            })
        },

        demote(admin) {
            if (! confirm(`Retirer les droits d'administrateur à ${admin.name} ? Le compte reste accessible en tant que client.`)) {
                return
            }

            router.delete(route('admin.admins.demote', admin.id), { preserveScroll: true })
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Administrateurs</h1>
    <p class="admin-sub">
        Ces comptes ouvrent la gestion du studio et reçoivent les emails de demande
        de rendez-vous. Ils se connectent par email et mot de passe, pas par lien magique.
    </p>

    <section class="card admin-card">
        <div class="row-actions">
            <button type="button" class="btn btn--ghost btn--sm" @click="open('create')">
                <Icon name="plus" :size="16" /> Nouveau compte
            </button>
            <button type="button" class="btn btn--ghost btn--sm" @click="open('promote')">
                <Icon name="user" :size="16" /> Promouvoir un compte existant
            </button>
        </div>

        <!-- Création d'un compte qui n'existe pas encore -->
        <div v-if="panel === 'create'" style="margin-top: 16px; border-top: 1px solid var(--border); padding-top: 16px">
            <div class="field-row">
                <label class="field">
                    <span class="field-label">Nom</span>
                    <input type="text" v-model="createForm.name" autocomplete="off">
                </label>
                <label class="field">
                    <span class="field-label">Email</span>
                    <input type="email" v-model="createForm.email" autocomplete="off">
                </label>
                <label class="field">
                    <span class="field-label">Téléphone</span>
                    <input type="tel" v-model="createForm.phone" autocomplete="off">
                </label>
            </div>

            <div class="field-row">
                <label class="field">
                    <span class="field-label">Mot de passe</span>
                    <input type="password" v-model="createForm.password" autocomplete="new-password">
                </label>
                <label class="field">
                    <span class="field-label">Confirmation</span>
                    <input type="password" v-model="createForm.password_confirmation" autocomplete="new-password">
                </label>
            </div>

            <p class="field-error" v-if="createForm.errors.name">{{ createForm.errors.name }}</p>
            <p class="field-error" v-if="createForm.errors.email">{{ createForm.errors.email }}</p>
            <p class="field-error" v-if="createForm.errors.password">{{ createForm.errors.password }}</p>

            <label class="check" style="margin: 16px 0">
                <input type="checkbox" v-model="createForm.notify">
                <span>Prévenir par email que l'accès est ouvert</span>
            </label>

            <p class="row-meta" style="margin-bottom: 14px">
                Le mot de passe n'est jamais envoyé par email : communiquez-le de vive voix.
            </p>

            <div class="row-actions">
                <button type="button" class="btn btn--solid btn--sm"
                        :disabled="createForm.processing" @click="createAdmin">Créer</button>
                <button type="button" class="btn btn--ghost btn--sm" @click="panel = null">Annuler</button>
            </div>
        </div>

        <!-- Promotion : l'adresse est déjà connue, on ne crée pas de doublon -->
        <div v-if="panel === 'promote'" style="margin-top: 16px; border-top: 1px solid var(--border); padding-top: 16px">
            <p class="row-meta" style="margin-bottom: 14px">
                Pour une personne qui a déjà un compte client : son historique de
                rendez-vous est conservé. Un mot de passe est nécessaire, un compte
                client n'en a pas.
            </p>

            <div class="field-row">
                <label class="field">
                    <span class="field-label">Email du compte</span>
                    <input type="email" v-model="promoteForm.email" autocomplete="off">
                </label>
                <label class="field">
                    <span class="field-label">Mot de passe</span>
                    <input type="password" v-model="promoteForm.password" autocomplete="new-password">
                </label>
                <label class="field">
                    <span class="field-label">Confirmation</span>
                    <input type="password" v-model="promoteForm.password_confirmation" autocomplete="new-password">
                </label>
            </div>

            <p class="field-error" v-if="promoteForm.errors.email">{{ promoteForm.errors.email }}</p>
            <p class="field-error" v-if="promoteForm.errors.password">{{ promoteForm.errors.password }}</p>

            <label class="check" style="margin: 16px 0">
                <input type="checkbox" v-model="promoteForm.notify">
                <span>Prévenir par email que l'accès est ouvert</span>
            </label>

            <div class="row-actions">
                <button type="button" class="btn btn--solid btn--sm"
                        :disabled="promoteForm.processing" @click="promoteAdmin">Donner les droits</button>
                <button type="button" class="btn btn--ghost btn--sm" @click="panel = null">Annuler</button>
            </div>
        </div>
    </section>

    <section class="card admin-card">
        <div class="row-list">
            <div class="row-item" v-for="admin in admins" :key="admin.id">
                <div class="row-main">
                    <p class="row-title">
                        {{ admin.name }}
                        <span v-if="admin.is_self" class="badge badge--green" style="margin-left: 8px">Vous</span>
                    </p>
                    <p class="row-meta">
                        {{ admin.email }}
                        <span v-if="admin.phone"> · {{ admin.phone }}</span>
                        <span v-if="admin.since"> · depuis le {{ admin.since }}</span>
                    </p>
                </div>

                <div class="row-actions">
                    <button type="button" class="btn btn--ghost btn--sm btn--danger"
                            :disabled="admin.is_self || isLast"
                            :title="admin.is_self
                                ? 'Vous ne pouvez pas retirer vos propres droits'
                                : (isLast ? 'Il doit rester au moins un administrateur' : '')"
                            @click="demote(admin)">Retirer les droits</button>
                </div>
            </div>
        </div>
    </section>
</template>
