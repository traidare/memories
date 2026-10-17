import { computed, ref, watch } from 'vue';

import { DaysFilterType } from '@services/API';
import { bus } from '@services/utils/event-bus';

import type { IFilters } from '@typings';

/** Share the active filters with header buttons recreated on layout changes. */
export const timelineFilters = ref<IFilters>({ embeddedTags: [] });

bus.on('memories:filters:changed', (newFilters) => {
  if (JSON.stringify(newFilters) === JSON.stringify(timelineFilters.value)) return;
  timelineFilters.value = newFilters;
});

/**
 * Embedded tag filters of a timeline
 * @param refresh Reloads the timeline after the filters changed
 */
export function useTimelineFilters(refresh: () => Promise<void>) {
  const filters = timelineFilters;

  /** Whether any filters are applied */
  const hasFilters = computed((): boolean => {
    return filters.value.embeddedTags.length > 0;
  });

  /** Apply new filters and reload the timeline */
  watch(filters, () => {
    void refresh();
  });

  /** Clear all filters and reload the timeline */
  function resetFilters() {
    // Go through the bus so the filter dropdown is reset as well
    bus.emit('memories:filters:changed', { embeddedTags: [] });
  }

  /** Add the filters to the query string of API calls */
  function setQuery(set: (filter: DaysFilterType, value: string) => void) {
    if (filters.value.embeddedTags.length > 0) {
      set(DaysFilterType.EMBEDDED_TAGS, filters.value.embeddedTags.map((tag) => encodeURIComponent(tag)).join(','));
    }
  }

  return { hasFilters, resetFilters, setQuery };
}
