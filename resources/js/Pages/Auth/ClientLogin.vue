<script>
import AuthLayout from '@/Layouts/AuthLayout.vue'
import Icon from '@/Components/Icon.vue'
import { useForm } from '@inertiajs/vue3'

/**
 * Connexion client : réseaux sociaux, ou lien magique pour les clients créés
 * au studio. L'accès administrateur passe par /login, volontairement séparé.
 */
export default {
    components: { AuthLayout, Icon },

    data() {
        return {
            providers: [
                { key: 'instagram', label: 'Instagram', icon: 'instagram' },
                { key: 'facebook',  label: 'Facebook',  icon: 'user' },
                { key: 'tiktok',    label: 'TikTok',    icon: 'tiktok' },
            ],
            form: useForm({ email: '' }),
            sent: false,
            failed: null,
        }
    },

    methods: {
        send() {
            console.log('Enter send')
            this.failed = null

            this.form.post(route('magic.send'), {
                preserveScroll: true,
                onSuccess: () => { this.sent = true; this.form.reset(); console.log('Success') },

                // Sans ce traitement, un refus — trop de tentatives, panne
                // réseau — laisse le formulaire figé sans le moindre signe.
                onError: (errors) => {
                    if (!errors.email) {
                        this.failed = 'Trop de tentatives ou service indisponible. Patientez une minute avant de réessayer.'
                    }
                },
            })
        },
    },
}
</script>

<template>
    <AuthLayout
        title="Connexion"
        subtitle="Connectez-vous pour réserver et suivre vos rendez-vous."
    >
        <div>
            <div class="stack">
                <a
                    v-for="provider in providers"
                    :key="provider.key"
                    :href="route('social.redirect', provider.key)"
                    class="btn btn--ghost btn--block"
                >
                    <Icon :name="provider.icon" :size="19" />
                    Continuer avec {{ provider.label }}
                </a>
            </div>

            <p class="center muted" style="margin: 22px 0; font-size: 13px">ou</p>

            <div v-if="sent" class="center">
                <Icon name="mail" :size="30" />
                <p class="row-title" style="margin: 12px 0 8px">Vérifiez votre boîte mail</p>
                <p class="muted" style="font-size: 13px">
                    Si un compte existe, un lien de connexion vient d'être envoyé.
                    Il est valable 30 minutes.
                </p>
                <button type="button" class="btn btn--ghost btn--block" style="margin-top: 16px"
                        @click="sent = false">Saisir une autre adresse</button>
            </div>

            <div v-else>
                <p class="field-help">
                    Si le studio a créé votre fiche, saisissez votre email : vous
                    recevrez un lien de connexion valable 30 minutes.
                </p>

                <p class="field-error" v-if="failed" style="margin-bottom: 14px">{{ failed }}</p>

                <label class="field">
                    <span class="field-label">Adresse email</span>
                    <input type="email" v-model="form.email" autocomplete="email"
                           placeholder="vous@exemple.com" @keyup.enter="send">
                    <span class="field-error" v-if="form.errors.email">{{ form.errors.email }}</span>
                </label>

                <button type="button" class="btn btn--solid btn--block"
                        :disabled="form.processing || !form.email" @click="send">
                    {{ form.processing ? 'Envoi…' : 'Recevoir mon lien' }}
                </button>
            </div>
        </div>

        <p class="center muted" style="margin-top: 22px; font-size: 12px">
            Vous êtes le barber ? La connexion se fait par mot de passe :
            <a :href="route('login')" class="auth-link">accès administrateur</a>.
        </p>
    </AuthLayout>
</template>
