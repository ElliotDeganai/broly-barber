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
    no_show:          'Client non venu',
}

const WARNING = ['refused', 'cancelled', 'counter_refused', 'no_show']
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

/** Le rendez-vous attend encore une décision. */
export function isPending(status) {
    return PENDING.includes(status)
}

/** Le rendez-vous n'aura pas lieu, ou n'a pas eu lieu. */
export function isWarning(status) {
    return WARNING.includes(status)
}

/**
 * Icône du statut, affichée sur les pavés du planning.
 *
 * La couleur du pavé étant prise par la prestation, le statut passe par la
 * forme : deux informations superposées sur la même teinte seraient illisibles.
 */
const ICONS = {
    pending:          'clock',
    counter_proposed: 'swap',
    confirmed:        'check',
    counter_accepted: 'check',
    completed:        'checkTwice',
    refused:          'closeRing',
    cancelled:        'closeRing',
    counter_refused:  'closeRing',
    no_show:          'userOff',
}

export function statusIcon(status) {
    return ICONS[status] || 'help'
}

export const STATUSES = Object.entries(LABELS).map(([value, label]) => ({ value, label }))

/**
 * Cases à cocher du filtre.
 *
 * Regroupées par libellé : « confirmé » et « créneau accepté » sont deux
 * valeurs en base mais une seule réalité pour le barbier, et deux cases
 * « Confirmé » côte à côte n'auraient aucun sens.
 */
export const STATUS_FILTERS = [
    { label: 'En attente',            icon: 'clock',      values: ['pending'] },
    { label: 'Autre créneau proposé', icon: 'swap',       values: ['counter_proposed'] },
    { label: 'Confirmé',              icon: 'check',      values: ['confirmed', 'counter_accepted'] },
    { label: 'Réalisé',               icon: 'checkTwice', values: ['completed'] },
    { label: 'Refusé',                icon: 'closeRing',  values: ['refused'] },
    { label: 'Proposition refusée',   icon: 'closeRing',  values: ['counter_refused'] },
    { label: 'Annulé',                icon: 'closeRing',  values: ['cancelled'] },
    { label: 'Client non venu',       icon: 'userOff',    values: ['no_show'] },
]

/** Légende des statuts du planning, dans l'ordre d'un rendez-vous qui avance. */
export const STATUS_LEGEND = [
    { icon: 'clock',      label: 'En attente' },
    { icon: 'swap',       label: 'Autre créneau proposé' },
    { icon: 'check',      label: 'Confirmé' },
    { icon: 'checkTwice', label: 'Réalisé' },
    { icon: 'closeRing',  label: 'Annulé ou refusé' },
    { icon: 'userOff',    label: 'Client non venu' },
]
