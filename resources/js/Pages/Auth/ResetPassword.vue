<script>
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { useForm } from '@inertiajs/vue3'

export default {
    components: { AuthLayout },

    props: {
        email: { type: String, required: true },
        token: { type: String, required: true },
    },

    data() {
        return {
            form: useForm({
                token: this.token,
                email: this.email,
                password: '',
                password_confirmation: '',
            }),
        }
    },

    methods: {
        submit() {
            this.form.post(route('password.store'), {
                onFinish: () => this.form.reset('password', 'password_confirmation'),
            })
        },
    },
}
</script>

<template>
    <AuthLayout title="Nouveau mot de passe">
        <label class="field">
            <span class="field-label">Adresse email</span>
            <input type="email" v-model="form.email" autocomplete="username">
            <span class="field-error" v-if="form.errors.email">{{ form.errors.email }}</span>
        </label>

        <label class="field">
            <span class="field-label">Nouveau mot de passe</span>
            <input type="password" v-model="form.password" autocomplete="new-password" autofocus>
            <span class="field-error" v-if="form.errors.password">{{ form.errors.password }}</span>
        </label>

        <label class="field">
            <span class="field-label">Confirmation</span>
            <input type="password" v-model="form.password_confirmation" autocomplete="new-password"
                   @keyup.enter="submit">
            <span class="field-error" v-if="form.errors.password_confirmation">
                {{ form.errors.password_confirmation }}
            </span>
        </label>

        <button type="button" class="btn btn--solid btn--block" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
    </AuthLayout>
</template>
