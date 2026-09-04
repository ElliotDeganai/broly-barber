<script>
import AuthLayout from '@/Layouts/AuthLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

/**
 * Inscription par mot de passe.
 *
 * Le parcours prévu passe par les réseaux sociaux ou le lien magique : cet
 * écran n'est là que parce que Breeze déclare la route. Pour le fermer,
 * retirer les routes « register » de routes/auth.php.
 */
export default {
    components: { AuthLayout, Link },

    data() {
        return {
            form: useForm({
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
            }),
        }
    },

    methods: {
        submit() {
            this.form.post(route('register'), {
                onFinish: () => this.form.reset('password', 'password_confirmation'),
            })
        },
    },
}
</script>

<template>
    <AuthLayout title="Créer un compte">
        <label class="field">
            <span class="field-label">Nom</span>
            <input type="text" v-model="form.name" autocomplete="name" autofocus>
            <span class="field-error" v-if="form.errors.name">{{ form.errors.name }}</span>
        </label>

        <label class="field">
            <span class="field-label">Adresse email</span>
            <input type="email" v-model="form.email" autocomplete="username">
            <span class="field-error" v-if="form.errors.email">{{ form.errors.email }}</span>
        </label>

        <label class="field">
            <span class="field-label">Mot de passe</span>
            <input type="password" v-model="form.password" autocomplete="new-password">
            <span class="field-error" v-if="form.errors.password">{{ form.errors.password }}</span>
        </label>

        <label class="field">
            <span class="field-label">Confirmation</span>
            <input type="password" v-model="form.password_confirmation" autocomplete="new-password"
                   @keyup.enter="submit">
        </label>

        <button type="button" class="btn btn--solid btn--block" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Création…' : 'Créer mon compte' }}
        </button>

        <div class="auth-separator">ou</div>

        <Link :href="route('client.login')" class="btn btn--ghost btn--block">
            Se connecter par réseau social
        </Link>

        <div class="auth-row">
            <Link :href="route('login')" class="auth-link">J'ai déjà un compte</Link>
        </div>
    </AuthLayout>
</template>
