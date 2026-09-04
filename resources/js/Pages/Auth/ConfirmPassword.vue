<script>
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { useForm } from '@inertiajs/vue3'

/** Reconfirmation avant une action sensible. */
export default {
    components: { AuthLayout },

    data() {
        return { form: useForm({ password: '' }) }
    },

    methods: {
        submit() {
            this.form.post(route('password.confirm'), {
                onFinish: () => this.form.reset(),
            })
        },
    },
}
</script>

<template>
    <AuthLayout
        title="Confirmation"
        subtitle="Cette zone est protégée. Saisissez votre mot de passe pour continuer."
    >
        <label class="field">
            <span class="field-label">Mot de passe</span>
            <input type="password" v-model="form.password" autocomplete="current-password" autofocus
                   @keyup.enter="submit">
            <span class="field-error" v-if="form.errors.password">{{ form.errors.password }}</span>
        </label>

        <button type="button" class="btn btn--solid btn--block" :disabled="form.processing" @click="submit">
            Confirmer
        </button>
    </AuthLayout>
</template>
