<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { router, useForm } from '@inertiajs/vue3'
import { money, todayInStudioTz } from '@/date'

/** Clients : création au studio, dettes, blocage, fidélité. */
export default {
    layout: AdminLayout,
    components: { Icon },

    props: {
        clients: { type: Object, required: true },
        tiers:   { type: Array,  default: () => [] },
        filter:  { type: String, default: null },
        search:  { type: String, default: null },
    },

    data() {
        return {
            q: this.search || '',
            createOpen: false,
            createForm: useForm({ name: '', email: '', phone: '' }),
            debtFor: null,
            debtForm: useForm({ mode: 'settle', amount: null, note: '' }),
            blockFor: null,
            blockForm: useForm({ reason: '' }),
        }
    },

    methods: {
        money,

        applyFilter(value) {
            router.get(route('admin.clients.index'), {
                filter: value || undefined,
                q: this.q || undefined,
            }, { preserveScroll: true })
        },

        submitSearch() {
            this.applyFilter(this.filter)
        },

        createClient() {
            this.createForm.post(route('admin.clients.store'), {
                preserveScroll: true,
                onSuccess: () => { this.createForm.reset(); this.createOpen = false },
            })
        },

        openDebt(client) {
            this.debtFor = client.id
            this.debtForm.mode = client.debt_amount > 0 ? 'settle' : 'add'
            this.debtForm.amount = null
            this.debtForm.note = client.debt_note || ''
        },

        saveDebt() {
            this.debtForm.post(route('admin.clients.debt', this.debtFor), {
                preserveScroll: true,
                onSuccess: () => { this.debtFor = null },
            })
        },

        toggleBlock(client) {
            if (client.is_blocked) {
                router.post(route('admin.clients.block', client.id), {}, { preserveScroll: true })
                return
            }

            this.blockFor = client.id
            this.blockForm.reason = ''
        },

        confirmBlock() {
            this.blockForm.post(route('admin.clients.block', this.blockFor), {
                preserveScroll: true,
                onSuccess: () => { this.blockFor = null },
            })
        },

        sendLink(id) {
            router.post(route('admin.clients.magic', id), {}, { preserveScroll: true })
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Clients</h1>
    <p class="admin-sub">
        Un client créé ici entre par lien magique : il n'a pas de mot de passe.
    </p>

    <section class="card admin-card">
        <div class="field-row">
            <label class="field">
                <span class="field-label">Rechercher</span>
                <input type="text" v-model="q" placeholder="Nom, email, téléphone"
                       @keyup.enter="submitSearch">
            </label>
            <label class="field">
                <span class="field-label">Filtrer</span>
                <select :value="filter || ''" @change="applyFilter($event.target.value)">
                    <option value="">Tous</option>
                    <option value="debt">En dette</option>
                    <option value="blocked">Bloqués</option>
                </select>
            </label>
        </div>

        <button type="button" class="btn btn--ghost btn--sm" @click="createOpen = !createOpen">
            <Icon name="plus" :size="16" /> Nouveau client
        </button>

        <div v-if="createOpen" style="margin-top: 16px; border-top: 1px solid var(--border); padding-top: 16px">
            <div class="field-row">
                <label class="field">
                    <span class="field-label">Nom</span>
                    <input type="text" v-model="createForm.name">
                </label>
                <label class="field">
                    <span class="field-label">Email</span>
                    <input type="email" v-model="createForm.email" placeholder="Pour l'envoi du lien d'accès">
                </label>
                <label class="field">
                    <span class="field-label">Téléphone</span>
                    <input type="tel" v-model="createForm.phone">
                </label>
            </div>
            <p class="field-error" v-if="createForm.errors.email">{{ createForm.errors.email }}</p>
            <button type="button" class="btn btn--solid btn--sm" @click="createClient">Créer</button>
        </div>
    </section>

    <section class="card admin-card">
        <div class="row-list" v-if="clients.data.length">
            <div class="row-item" v-for="client in clients.data" :key="client.id">
                <div class="row-main">
                    <p class="row-title">
                        {{ client.name }}
                        <span class="badge badge--green" style="margin-left: 8px"
                              :style="{ color: client.tier.color, borderColor: client.tier.color }">
                            {{ client.tier.label }}
                        </span>
                    </p>
                    <p class="row-meta">
                        {{ client.visits_count }} visite(s)
                        <span v-if="client.email"> · {{ client.email }}</span>
                        <span v-if="client.phone"> · {{ client.phone }}</span>
                    </p>
                    <p class="row-meta" v-if="client.debt_amount > 0" style="color: var(--danger)">
                        Dette {{ money(client.debt_amount) }}
                        <span v-if="client.debt_note">— {{ client.debt_note }}</span>
                    </p>
                    <p class="row-meta" v-if="client.is_blocked" style="color: var(--danger)">
                        Bloqué<span v-if="client.block_reason"> — {{ client.block_reason }}</span>
                    </p>
                </div>

                <div class="row-actions">
                    <button type="button" class="btn btn--ghost btn--sm" @click="openDebt(client)">Dette</button>
                    <button type="button" class="btn btn--ghost btn--sm" @click="toggleBlock(client)">
                        {{ client.is_blocked ? 'Débloquer' : 'Bloquer' }}
                    </button>
                    <button v-if="client.email" type="button" class="btn btn--ghost btn--sm"
                            @click="sendLink(client.id)">Envoyer l'accès</button>
                </div>

                <div v-if="debtFor === client.id" style="flex: 1 1 100%; border-top: 1px solid var(--border); padding-top: 14px">
                    <div class="field-row">
                        <label class="field">
                            <span class="field-label">Opération</span>
                            <select v-model="debtForm.mode">
                                <option value="add">Ajouter un montant</option>
                                <option value="settle">Encaisser un règlement</option>
                                <option value="set">Fixer le montant total</option>
                            </select>
                        </label>
                        <label class="field">
                            <span class="field-label">Montant (€)</span>
                            <input type="number" step="0.01" min="0" v-model="debtForm.amount">
                        </label>
                        <label class="field">
                            <span class="field-label">Motif</span>
                            <input type="text" v-model="debtForm.note">
                        </label>
                    </div>
                    <div class="row-actions">
                        <button type="button" class="btn btn--solid btn--sm" @click="saveDebt">Enregistrer</button>
                        <button type="button" class="btn btn--ghost btn--sm" @click="debtFor = null">Annuler</button>
                    </div>
                </div>

                <div v-if="blockFor === client.id" style="flex: 1 1 100%; border-top: 1px solid var(--border); padding-top: 14px">
                    <label class="field">
                        <span class="field-label">Motif du blocage</span>
                        <input type="text" v-model="blockForm.reason" placeholder="Visible en interne">
                    </label>
                    <div class="row-actions">
                        <button type="button" class="btn btn--solid btn--sm" @click="confirmBlock">Bloquer</button>
                        <button type="button" class="btn btn--ghost btn--sm" @click="blockFor = null">Annuler</button>
                    </div>
                </div>
            </div>
        </div>

        <p v-else class="empty-state">Aucun client ne correspond.</p>
    </section>
</template>
