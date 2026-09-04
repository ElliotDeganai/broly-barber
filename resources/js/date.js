/**
 * Formatage des dates, centralisé.
 *
 * Tout passe par ce fichier, jamais par `toLocaleString` en direct : le fuseau
 * du studio est forcé, sinon un client à l'étranger verrait des horaires
 * décalés par rapport à ceux du salon.
 */
export const STUDIO_TZ = 'Europe/Paris'

const opts = (extra) => ({ timeZone: STUDIO_TZ, ...extra })

export function formatDate(value) {
    return new Date(value).toLocaleDateString('fr-FR', opts({
        weekday: 'short', day: 'numeric', month: 'short',
    }))
}

export function formatDateLong(value) {
    return new Date(value).toLocaleDateString('fr-FR', opts({
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    }))
}

export function formatTime(value) {
    return new Date(value).toLocaleTimeString('fr-FR', opts({ hour: '2-digit', minute: '2-digit' }))
}

export function formatDateTime(value) {
    return `${formatDate(value)}, ${formatTime(value)}`
}

export function formatMonth(value) {
    return new Date(value).toLocaleDateString('fr-FR', opts({ month: 'long', year: 'numeric' }))
}

/** Date du jour au format Y-m-d, dans le fuseau du studio. */
export function todayInStudioTz() {
    // Format suédois : il produit exactement YYYY-MM-DD
    return new Date().toLocaleDateString('sv-SE', { timeZone: STUDIO_TZ })
}

/** Format attendu par le serveur : Y-m-d H:i, sans indicateur de fuseau. */
export function toServerDateTime(day, time) {
    return `${day} ${time}:00`
}

export function money(value) {
    return `${Number(value ?? 0).toFixed(2).replace('.', ',')} €`
}
