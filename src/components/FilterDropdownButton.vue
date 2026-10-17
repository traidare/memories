<template>
  <div class="filter-container">
    <NcPopover v-model:shown="shown" popup-role="dialog" placement="bottom-end">
      <template #trigger>
        <NcButton
          variant="tertiary-no-background"
          :title="t('memories', 'Filter photos')"
          :aria-label="t('memories', 'Filter photos')"
          :disabled="disabled"
          class="filter-button"
          :class="{ active: hasFilters }"
        >
          <template #icon>
            <FilterIcon :size="20" />
          </template>
        </NcButton>
      </template>
      <FilterComponent
        :disabled="disabled"
        :initial-filters="timelineFilters"
        @keydown.esc.stop.prevent="shown = false"
      />
    </NcPopover>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import NcButton from '@nextcloud/vue/components/NcButton';
import NcPopover from '@nextcloud/vue/components/NcPopover';
import FilterIcon from 'vue-material-design-icons/FilterMenu.vue';
import FilterComponent from './FilterComponent.vue';

import { t } from '@services/l10n';
import { timelineFilters } from '@services/timeline-filters';

withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false });

const shown = ref(false);
const hasFilters = computed(() => timelineFilters.value.embeddedTags.length > 0);
</script>

<style lang="scss" scoped>
.filter-container {
  z-index: 1000;

  .filter-button {
    header#header & {
      color: var(--color-background-plain-text, var(--color-primary-text)) !important;
    }

    &.active {
      background-color: var(--color-primary-element);
      color: var(--color-primary-element-text);
    }
  }
}
</style>
