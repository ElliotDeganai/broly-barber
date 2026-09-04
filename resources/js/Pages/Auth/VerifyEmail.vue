<script>
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

export default {
    components: { AuthLayout, Link },

    props: { status: { type: String, default: null } },

    data() {
        return { form: useForm({}) }
    },

    computed: {
        justSent() {
            return this.status === 'verification-link-sent'
        },
    },

    methods: {
        submit() {
            this.form.post(route('verification.send'))
        },
        logout() {
            this.$inertia.post(route('logout'))
        },
    },
}
</script>

<template>
    <AuthLayout
        title="Vérifiez votre email"
        subtitle="Un lien de vérification vient d'être envoyé à l'adresse indiquée lors de l'inscription."
    >
        <p v-if="justSent" class="auth-status">
            Un nouveau lien de vérification vient d'être envoyé.
        </p>

        <button type="button" class="btn btn--solid btn--block" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Envoi…' : 'Renvoyer le lien' }}
        </button>

        <div class="auth-row">
            <button type="button" class="auth-link" style="background: none; border: none; padding: 0"
                    @click="logout">Se déconnecter</button>
        </div>
    </AuthLayout>
</template>
