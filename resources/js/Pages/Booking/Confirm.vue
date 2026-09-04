<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { formatDateLong, formatTime, money } from '@/date'

/**
 * Étape 3 : récapitulatif, conditions et envoi.
 *
 * Le prix affiché ici est recalculé par le serveur, jamais repris du
 * formulaire — et il sera revérifié à la validation.
 */
export default {
    layout: AppLayout,
    components: { Link },

    props: {
        service:  { type: Object, required: true },
        startsAt: { type: String, required: true },
        quote:    { type: Object, default: () => ({}) },
        terms:    { type: Object, default: () => ({}) },
    },

    data() {
        return {
            form: useForm({
                starts_at: this.startsAt,
                comment: '',
                terms_accepted: false,
            }),
            showTerms: false,
        }
    },

    methods: {
        formatDateLong,
        formatTime,
        money,

        submit() {
            this.form.post(route('booking.store', this.service.slug))
        },
    },
}
</script>

<template>
    <div class="shell section" style="max-width: 620px">
        <p class="badge badge--green">Étape 3 sur 3</p>
        <h1 class="title-glow" style="margin: 14px 0 24px">Confirmer la demande</h1>

        <section class="card admin-card">
            <h2 class="admin-card-title">Votre rendez-vous</h2>

            <p class="row-title" style="font-size: 18px">{{ formatDateLong(startsAt) }}</p>
            <p class="row-meta" style="margin-bottom: 16px">
                {{ formatTime(startsAt) }} · {{ service.name }}
                <span v-if="service.includes">({{ service.includes }})</span>
                · {{ service.duration_min }} min
            </p>

            <p class="row-meta" v-if="service.description">{{ service.description }}</p>

            <div class="price-row" style="border-top: 1px solid var(--border); padding-top: 14px; margin-top: 14px;
                        display: flex; justify-content: space-between; align-items: baseline">
                <span>Prestation</span>
                <strong class="service-price">{{ money(quote.service_price) }}</strong>
            </div>

            <p class="badge badge--yellow" v-if="quote.is_late_night" style="margin-top: 10px">
                Créneau après {{ quote.late_hour }}h — tarif ×{{ quote.multiplier }}
            </p>

            <div v-if="quote.debt > 0" style="margin-top: 14px">
                <div style="display: flex; justify-content: space-between; align-items: baseline">
                    <span style="color: var(--danger)">Dette en cours</span>
                    <strong style="color: var(--danger)">{{ money(quote.debt) }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: baseline;
                            border-top: 1px solid var(--border); padding-top: 12px; margin-top: 12px">
                    <span>Total à régler au studio</span>
                    <strong class="service-price">{{ money(quote.total_due) }}</strong>
                </div>
            </div>
        </section>

        <section class="card admin-card">
            <label class="field">
                <span class="field-label">Message (facultatif)</span>
                <p class="field-help">Une précision, une photo d'inspiration à montrer sur place…</p>
                <textarea rows="3" v-model="form.comment"></textarea>
            </label>

            <label class="check check--top">
                <input type="checkbox" v-model="form.terms_accepted">
                <span>
                    J'accepte les
                    <button type="button" style="background: none; border: none; color: var(--green);
                            text-decoration: underline; padding: 0" @click="showTerms = !showTerms">
                        conditions de réservation
                    </button>
                </span>
            </label>
            <p class="field-error" v-if="form.errors.terms_accepted">{{ form.errors.terms_accepted }}</p>

            <div v-if="showTerms" class="field-help" style="margin-top: 14px; border-top: 1px solid var(--border); padding-top: 14px">
                <p>
                    Votre demande est soumise à validation par le barber. Une réponse
                    est apportée sous {{ terms.response_hours }} heures : le créneau
                    n'est définitivement réservé qu'après confirmation.
                </p>
                <p>
                    L'annulation est possible jusqu'à {{ terms.cancellation_hours }} heures
                    avant le rendez-vous, depuis votre espace client.
                </p>
                <p>
                    Le règlement s'effectue exclusivement au studio. Aucun paiement
                    n'est encaissé en ligne.
                </p>
            </div>

            <button type="button" class="btn btn--solid btn--block" style="margin-top: 20px"
                    :disabled="form.processing || !form.terms_accepted" @click="submit">
                {{ form.processing ? 'Envoi…' : 'Envoyer ma demande' }}
            </button>

            <Link :href="route('booking.slots', service.slug)" class="btn btn--ghost btn--block"
                  style="margin-top: 10px">Changer de créneau</Link>
        </section>
    </div>
</template>
