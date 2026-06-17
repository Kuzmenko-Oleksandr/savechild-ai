import { ref } from 'vue';

// Shared so the layout navbar can square its bottom corners while the
// full-width filter panel is open (and round them again when closed).
export const filterPanelOpen = ref(false);
