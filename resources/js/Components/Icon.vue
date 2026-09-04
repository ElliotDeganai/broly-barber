<script>
/**
 * Icônes SVG inline : aucune police d'icônes à charger, donc rien qui clignote
 * au premier rendu et aucun appel vers un tiers.
 */
const PATHS = {
    scissors: '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M8.6 7.6 20 18M8.6 16.4 20 6"/>',
    calendar: '<rect x="3.5" y="5.5" width="17" height="15" rx="2"/><path d="M8 3v4M16 3v4M3.5 10h17"/>',
    home:     '<path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z"/>',
    login:    '<path d="M14 8V5.5A1.5 1.5 0 0 0 12.5 4h-6A1.5 1.5 0 0 0 5 5.5v13A1.5 1.5 0 0 0 6.5 20h6a1.5 1.5 0 0 0 1.5-1.5V16M10 12h10M17 9l3 3-3 3"/>',
    user:     '<circle cx="12" cy="8" r="4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
    logout:   '<path d="M14 8V5.5A1.5 1.5 0 0 0 12.5 4h-6A1.5 1.5 0 0 0 5 5.5v13A1.5 1.5 0 0 0 6.5 20h6a1.5 1.5 0 0 0 1.5-1.5V16M10 12h10M17 9l3 3-3 3"/>',
    check:    '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
    clock:    '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5l3.5 2"/>',
    alert:    '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/>',
    plus:     '<path d="M12 5v14M5 12h14"/>',
    close:    '<path d="M6 6l12 12M18 6L6 18"/>',
    left:     '<path d="M15 5l-7 7 7 7"/>',
    right:    '<path d="M9 5l7 7-7 7"/>',
    zoom:     '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5M11 8v6M8 11h6"/>',
    photo:    '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="M4 17l5-5 4 4 3-3 4 4"/>',
    bag:      '<path d="M5 8h14l-1.2 11.2a1.5 1.5 0 0 1-1.5 1.3H7.7a1.5 1.5 0 0 1-1.5-1.3z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
    mail:     '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    instagram: '<rect x="4" y="4" width="16" height="16" rx="4"/><circle cx="12" cy="12" r="3.4"/><circle cx="16.8" cy="7.2" r=".9" fill="currentColor" stroke="none"/>',
    tiktok:   '<path d="M15 4c.6 2.2 2 3.5 4.2 3.8v3c-1.6.1-3-.4-4.2-1.3v5.9a5.4 5.4 0 1 1-4.6-5.3v3.1a2.4 2.4 0 1 0 1.6 2.2V4z"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M12 2.5l1.6 2.6 3-.6.5 3 2.7 1.5-1.6 2.6 1.6 2.6-2.7 1.5-.5 3-3-.6L12 21.5l-1.6-2.6-3 .6-.5-3L4.2 15l1.6-2.6L4.2 9.8l2.7-1.5.5-3 3 .6z"/>',
    trash:    '<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/>',
    pencil:   '<path d="M4 20h4L18.5 9.5l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
    undo:     '<path d="M4 8h10a5 5 0 0 1 0 10h-6M4 8l4-4M4 8l4 4"/>',
    redo:     '<path d="M20 8H10a5 5 0 0 0 0 10h6M20 8l-4-4M20 8l-4 4"/>',
    underline: '<path d="M7 4v7a5 5 0 0 0 10 0V4M5 20h14"/>',
    strike:   '<path d="M5 12h14M8.5 8a3.5 3.5 0 0 1 7 0M15 16a3.5 3.5 0 0 1-7 0"/>',
    listOl:   '<path d="M10 6h10M10 12h10M10 18h10M4 5.5h1.5V9M4 9h2M4 14.5h2V16H4v1.5h2"/>',
    paragraph: '<path d="M13 5v14M17 5v14M13 5h-3.5a3.5 3.5 0 0 0 0 7H13"/>',
    minus:    '<path d="M5 12h14"/>',
    unlink:   '<path d="M9 15l-1.3 1.3a4 4 0 0 1-5.7-5.7L3.3 9.3M15 9l1.3-1.3a4 4 0 0 1 5.7 5.7L20.7 14.7M4 4l16 16"/>',
    bold:     '<path d="M7 5h5.5a3.5 3.5 0 0 1 0 7H7zM7 12h6.5a3.5 3.5 0 0 1 0 7H7z"/>',
    italic:   '<path d="M14 5h-4M14 19h-4M15 5l-4 14"/>',
    h2:       '<path d="M4 6v12M11 6v12M4 12h7M15.5 10a2.2 2.2 0 1 1 4 1.3L15.5 18h4.5"/>',
    h3:       '<path d="M4 6v12M11 6v12M4 12h7M15.5 9.5a2 2 0 1 1 2.3 2.5 2 2 0 1 1-2.3 2.5"/>',
    list:     '<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
    quote:    '<path d="M9 7H5.5A1.5 1.5 0 0 0 4 8.5V12h5V7zM9 12c0 3-1.5 4.5-4 5M20 7h-3.5A1.5 1.5 0 0 0 15 8.5V12h5V7zM20 12c0 3-1.5 4.5-4 5"/>',
    link:     '<path d="M10 13a4 4 0 0 0 5.7.3l2.6-2.6a4 4 0 0 0-5.7-5.7L11.2 6.4M14 11a4 4 0 0 0-5.7-.3l-2.6 2.6a4 4 0 0 0 5.7 5.7l1.3-1.3"/>',
    eraser:   '<path d="M8 20H5l-1.6-1.6a2 2 0 0 1 0-2.8l9-9a2 2 0 0 1 2.8 0l4.2 4.2a2 2 0 0 1 0 2.8L12 20zM14 20h6M8.5 10.5l5 5"/>',
    help:     '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.2 2.4c-.7.2-1.2.9-1.2 1.6v.5M12 17h.01"/>',
}

export default {
    props: {
        name: { type: String, required: true },
        size: { type: [Number, String], default: 20 },
    },

    computed: {
        paths() {
            return PATHS[this.name] || PATHS.help
        },
    },
}
</script>

<template>
    <svg
        :width="size" :height="size" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" stroke-width="1.8"
        stroke-linecap="round" stroke-linejoin="round"
        aria-hidden="true" v-html="paths"
    />
</template>
