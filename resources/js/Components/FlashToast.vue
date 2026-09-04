<script>
import Icon from '@/Components/Icon.vue'

/**
 * Notification flottante.
 *
 * Fixée à l'écran plutôt qu'en haut de page : après avoir enregistré un
 * formulaire long, l'utilisateur est en bas et ne verrait jamais un message
 * placé dans le flux du document.
 *
 * Les messages s'empilent et disparaissent seuls. Un message d'erreur reste
 * plus longtemps : on ne veut pas qu'il s'efface avant d'avoir été lu.
 */
export default {
    components: { Icon },

    data() {
        return { toasts: [] }
    },

    computed: {
        flash() {
            return this.$page.props.flash || {}
        },
    },

    watch: {
        // immediate : capte aussi le message présent au premier rendu, après
        // une redirection depuis un autre écran.
        flash: {
            handler(value) {
                if (value?.success) this.push('success', value.success)
                if (value?.error) this.push('error', value.error)
            },
            immediate: true,
            deep: true,
        },
    },

    beforeUnmount() {
        this.toasts.forEach((t) => clearTimeout(t.timer))
    },

    methods: {
        push(type, message) {
            // Un même message renvoyé deux fois de suite ne s'affiche qu'une fois
            if (this.toasts.some((t) => t.message === message && t.type === type)) return

            const id = Date.now() + Math.random()
            const delay = type === 'error' ? 8000 : 4500

            this.toasts.push({
                id,
                type,
                message,
                timer: setTimeout(() => this.dismiss(id), delay),
            })
        },

        dismiss(id) {
            const index = this.toasts.findIndex((t) => t.id === id)
            if (index === -1) return

            clearTimeout(this.toasts[index].timer)
            this.toasts.splice(index, 1)
        },
    },
}
</script>

<template>
    <div class="toast-stack" role="status" aria-live="polite">
        <TransitionGroup name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="toast"
                :class="`toast--${toast.type}`"
                @click="dismiss(toast.id)"
            >
                <Icon :name="toast.type === 'error' ? 'alert' : 'check'" :size="19" />
                <span class="toast-text">{{ toast.message }}</span>
                <button type="button" class="toast-close" aria-label="Fermer"
                        @click.stop="dismiss(toast.id)">
                    <Icon name="close" :size="15" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
