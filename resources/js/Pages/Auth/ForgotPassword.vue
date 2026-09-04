<script>
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthLayout, Link },

    props: { status: { type: String, default: null } },

    data() {
        return { form: useForm({ email: '' }) }
    },

    methods: {
        submit() {
            this.form.post(route('password.email'))
        },
    },
}
</script>

<template>
    <AuthLayout
        title="Mot de passe oublié"
        subtitle="Saisissez votre adresse : un lien de réinitialisation vous sera envoyé."
    >
        <p v-if="status" class="auth-status">{{ status }}</p>

        <label class="field">
            <span class="field-label">Adresse email</span>
            <input type="email" v-model="form.email" autocomplete="username" autofocus
                   @keyup.enter="submit">
            <span class="field-error" v-if="form.errors.email">{{ form.errors.email }}</span>
        </label>

        <button type="button" class="btn btn--solid btn--block" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Envoi…' : 'Envoyer le lien' }}
        </button>

        <div class="auth-row">
            <Link :href="route('login')" class="auth-link">Retour à la connexion</Link>
        </div>
    </AuthLayout>
</template>
