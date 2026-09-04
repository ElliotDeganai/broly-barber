/**
 * Libellés des statuts de rendez-vous.
 *
 * Les valeurs stockées en base sont en anglais ; elles ne doivent jamais
 * s'afficher telles quelles. Ce module est le seul endroit où la traduction
 * existe — deux tables séparées finiraient par diverger.
 */
const LABELS = {
    pending:          'En attente',
    confirmed:        'Confirmé',
    refused:          'Refusé',
    cancelled:        'Annulé',
    counter_proposed: 'Autre créneau proposé',
    counter_accepted: 'Confirmé',
    counter_refused:  'Proposition refusée',
    completed:        'Réalisé',
}

const WARNING = ['refused', 'cancelled', 'counter_refused']
const PENDING = ['pending', 'counter_proposed']

export function statusLabel(status) {
    return LABELS[status] || status
}

export function statusClass(status) {
    return {
        'badge--yellow': PENDING.includes(status),
        'badge--danger': WARNING.includes(status),
        'badge--green':  !PENDING.includes(status) && !WARNING.includes(status),
    }
}

export const STATUSES = Object.entries(LABELS).map(([value, label]) => ({ value, label }))
