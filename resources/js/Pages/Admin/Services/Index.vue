<script>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Icon from '@/Components/Icon.vue'
import { Link, router } from '@inertiajs/vue3'
import { money } from '@/date'

export default {
    layout: AdminLayout,
    components: { Icon, Link },

    props: { services: { type: Array, default: () => [] } },

    methods: {
        money,
        remove(service) {
            if (confirm(`Supprimer « ${service.name} » ?`)) {
                router.delete(route('admin.services.destroy', service.id), { preserveScroll: true })
            }
        },
    },
}
</script>

<template>
    <h1 class="admin-title">Prestations</h1>
    <p class="admin-sub">
        La durée pilote le calcul des créneaux : une prestation de 45 minutes
        n'apparaît que là où 45 minutes sont libres.
    </p>

    <Link :href="route('admin.services.create')" class="btn btn--solid btn--sm" style="margin-bottom: 20px">
        <Icon name="plus" :size="16" /> Nouvelle prestation
    </Link>

    <section class="card admin-card">
        <div class="row-list" v-if="services.length">
            <div class="row-item" v-for="service in services" :key="service.id">
                <div class="thumb" style="flex: 0 0 64px; width: 64px" v-if="service.image">
                    <img :src="service.image" :alt="service.name" loading="lazy" decoding="async">
                </div>

                <div class="row-main">
                    <p class="row-title">{{ service.name }}</p>
                    <p class="row-meta">
                        <span v-if="service.includes">{{ service.includes }} · </span>
                        {{ service.duration_min }} min · {{ money(service.price) }}
                        · {{ service.has_restructuration ? 'avec restructuration' : 'sans restructuration' }}
                    </p>
                </div>

                <span class="badge" :class="service.is_active ? 'badge--green' : 'badge--danger'">
                    {{ service.is_active ? 'Active' : 'Inactive' }}
                </span>

                <div class="row-actions">
                    <Link :href="route('admin.services.edit', service.id)" class="btn-icon" aria-label="Modifier">
                        <Icon name="pencil" :size="16" />
                    </Link>
                    <button type="button" class="btn-icon btn-icon--danger" aria-label="Supprimer"
                            @click="remove(service)">
                        <Icon name="trash" :size="16" />
                    </button>
                </div>
            </div>
        </div>

        <p v-else class="empty-state">Aucune prestation.</p>
    </section>
</template>
