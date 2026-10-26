/**
 * Formatage des dates, centralisé.
 *
 * Tout passe par ce fichier, jamais par `toLocaleString` en direct : le fuseau
 * du studio est forcé, sinon un client à l'étranger verrait des horaires
 * décalés par rapport à ceux du salon.
 */
export const STUDIO_TZ = 'Europe/Paris'

const opts = (extra) => ({ timeZone: STUDIO_TZ, ...extra })

/**
 * Date longue, avec l'ordinal du premier du mois.
 *
 * « 1 novembre » n'existe pas en français ; toLocaleDateString ne connaît pas
 * l'exception, on reconstruit donc la chaîne à partir de ses morceaux plutôt
 * que de bricoler une expression régulière sur le résultat — « 1 » pourrait
 * aussi bien être le début d'une année.
 */
function enClair(value, extra) {
    return new Intl.DateTimeFormat('fr-FR', opts(extra))
        .formatToParts(new Date(value))
        .map((part) => (part.type === 'day' && part.value === '1' ? '1er' : part.value))
        .join('')
}

export function formatDate(value) {
    return enClair(value, { weekday: 'short', day: 'numeric', month: 'short' })
}

export function formatDateLong(value) {
    return enClair(value, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
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
