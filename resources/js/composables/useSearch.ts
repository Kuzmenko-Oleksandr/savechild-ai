import { ref } from 'vue';

// Module-level shared state: the header search box writes here,
// list pages read it to filter their rows.
export const searchQuery = ref('');

export function useSearch() {
    return { searchQuery };
}
