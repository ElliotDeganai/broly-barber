<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link, useForm, router } from '@inertiajs/vue3'

/**
 * Profil : informations, mot de passe, suppression du compte.
 *
 * Le layout suit le rôle : le barber reste dans son back office, le client
 * garde la navigation du site. Passer d'un univers à l'autre en modifiant son
 * mot de passe serait déroutant.
 */
export default {
    // Le layout est choisi au rendu, d'après le rôle de l'utilisateur
    layout: (h, page) => {
        const isAdmin = page.props.auth?.permissions?.manage_content

        return h(isAdmin ? AdminLayout : AppLayout, () => page)
    },

    components: { Link },

    props: {
        mustVerifyEmail: { type: Boolean, default: false },
        status:          { type: String,  default: null },
    },

    data() {
        const user = this.$page.props.auth.user

        return {
            profileForm: useForm({ name: user?.name || '', email: user?.email || '' }),
            passwordForm: useForm({
                current_password: '',
                password: '',
                password_confirmation: '',
            }),
            deleteForm: useForm({ password: '' }),
            confirmingDeletion: false,
        }
    },

    computed: {
        user() {
            return this.$page.props.auth.user
        },
    },

    methods: {
        saveProfile() {
            this.profileForm.patch(route('profile.update'), { preserveScroll: true })
        },

        savePassword() {
            this.passwordForm.put(route('password.update'), {
                preserveScroll: true,
                onSuccess: () => this.passwordForm.reset(),
                onError: () => {
                    // Le mot de passe fautif est effacé : le retaper évite de
                    // renvoyer par erreur la même valeur invalide.
                    if (this.passwordForm.errors.password) {
                        this.passwordForm.reset('password', 'password_confirmation')
                    }
                    if (this.passwordForm.errors.current_password) {
                        this.passwordForm.reset('current_password')
                    }
                },
            })
        },

        deleteAccount() {
            this.deleteForm.delete(route('profile.destroy'), {
                preserveScroll: true,
                onSuccess: () => { this.confirmingDeletion = false },
            })
        },

        resendVerification() {
            router.post(route('verification.send'), {}, { preserveScroll: true })
        },
    },
}
</script>

<template>
    <div class="shell section">
        <h1 class="admin-title">Mon profil</h1>
        <p class="admin-sub">Vos informations, votre mot de passe et votre compte.</p>

        <section class="card admin-card">
            <h2 class="admin-card-title">Informations</h2>

            <label class="field">
                <span class="field-label">Nom</span>
                <input type="text" v-model="profileForm.name" autocomplete="name">
                <span class="field-error" v-if="profileForm.errors.name">{{ profileForm.errors.name }}</span>
            </label>

            <label class="field">
                <span class="field-label">Adresse email</span>
                <input type="email" v-model="profileForm.email" autocomplete="username">
                <span class="field-error" v-if="profileForm.errors.email">{{ profileForm.errors.email }}</span>
            </label>

            <p class="field-help" v-if="mustVerifyEmail && user && !user.email_verified_at">
                Votre adresse n'est pas vérifiée.
                <button type="button" class="auth-link" style="background: none; border: none; padding: 0"
                        @click="resendVerification">Renvoyer le lien de vérification</button>
            </p>

            <p class="auth-status" v-if="status === 'verification-link-sent'">
                Un nouveau lien de vérification vient d'être envoyé.
            </p>

            <div class="row-actions">
                <button type="button" class="btn btn--solid btn--sm" :disabled="profileForm.processing"
                        @click="saveProfile">Enregistrer</button>
                <span class="muted" style="font-size: 13px" v-if="profileForm.recentlySuccessful">
                    Enregistré.
                </span>
            </div>
        </section>

        <section class="card admin-card">
            <h2 class="admin-card-title">Mot de passe</h2>
            <p class="field-help">Choisissez un mot de passe long, utilisé nulle part ailleurs.</p>

            <label class="field">
                <span class="field-label">Mot de passe actuel</span>
                <input type="password" v-model="passwordForm.current_password" autocomplete="current-password">
                <span class="field-error" v-if="passwordForm.errors.current_password">
                    {{ passwordForm.errors.current_password }}
                </span>
            </label>

            <label class="field">
                <span class="field-label">Nouveau mot de passe</span>
                <input type="password" v-model="passwordForm.password" autocomplete="new-password">
                <span class="field-error" v-if="passwordForm.errors.password">
                    {{ passwordForm.errors.password }}
                </span>
            </label>

            <label class="field">
                <span class="field-label">Confirmation</span>
                <input type="password" v-model="passwordForm.password_confirmation" autocomplete="new-password">
            </label>

            <div class="row-actions">
                <button type="button" class="btn btn--solid btn--sm" :disabled="passwordForm.processing"
                        @click="savePassword">Changer</button>
                <span class="muted" style="font-size: 13px" v-if="passwordForm.recentlySuccessful">
                    Mot de passe modifié.
                </span>
            </div>
        </section>

        <section class="card admin-card" style="border-color: var(--danger-border)">
            <h2 class="admin-card-title" style="color: var(--danger)">Supprimer mon compte</h2>
            <p class="field-help">
                La suppression est définitive. Vos rendez-vous et votre historique de
                fidélité seront effacés.
            </p>

            <button v-if="!confirmingDeletion" type="button" class="btn btn--ghost btn--sm"
                    style="border-color: var(--danger-border); color: var(--danger)"
                    @click="confirmingDeletion = true">Supprimer mon compte</button>

            <template v-else>
                <label class="field">
                    <span class="field-label">Confirmez avec votre mot de passe</span>
                    <input type="password" v-model="deleteForm.password" autocomplete="current-password">
                    <span class="field-error" v-if="deleteForm.errors.password">
                        {{ deleteForm.errors.password }}
                    </span>
                </label>

                <div class="row-actions">
                    <button type="button" class="btn btn--sm"
                            style="background: var(--danger); color: #1A0000"
                            :disabled="deleteForm.processing" @click="deleteAccount">
                        Supprimer définitivement
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm"
                            @click="confirmingDeletion = false">Annuler</button>
                </div>
            </template>
        </section>
    </div>
</template>
