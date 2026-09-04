<script>
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

/**
 * Connexion administrateur, par email et mot de passe.
 * Les clients passent par /connexion : réseaux sociaux ou lien magique.
 */
export default {
    components: { AuthLayout, Link },

    props: {
        canResetPassword: { type: Boolean, default: false },
        status:           { type: String,  default: null },
    },

    data() {
        return {
            form: useForm({ email: '', password: '', remember: false }),
        }
    },

    methods: {
        submit() {
            this.form.post(route('login'), {
                onFinish: () => this.form.reset('password'),
            })
        },
    },
}
</script>

<template>
    <AuthLayout title="Accès barber" subtitle="Réservé à la gestion du studio.">
        <p v-if="status" class="auth-status">{{ status }}</p>

        <label class="field">
            <span class="field-label">Adresse email</span>
            <input type="email" v-model="form.email" autocomplete="username" autofocus
                   @keyup.enter="submit">
            <span class="field-error" v-if="form.errors.email">{{ form.errors.email }}</span>
        </label>

        <label class="field">
            <span class="field-label">Mot de passe</span>
            <input type="password" v-model="form.password" autocomplete="current-password"
                   @keyup.enter="submit">
            <span class="field-error" v-if="form.errors.password">{{ form.errors.password }}</span>
        </label>

        <label class="check">
            <input type="checkbox" v-model="form.remember">
            <span>Rester connecté</span>
        </label>

        <button type="button" class="btn btn--solid btn--block" style="margin-top: 22px"
                :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Connexion…' : 'Se connecter' }}
        </button>

        <div class="auth-row">
            <Link v-if="canResetPassword" :href="route('password.request')" class="auth-link">
                Mot de passe oublié ?
            </Link>
            <Link :href="route('client.login')" class="auth-link">Je suis client</Link>
        </div>
    </AuthLayout>
</template>
