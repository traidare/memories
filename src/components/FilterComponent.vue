<template>
  <div class="filter-component">
    <form @submit.prevent>
      <!-- Minimum Rating Filter -->
      <div class="filter-section">
        <label class="filter-label">
          {{ t('memories', 'Minimum Rating') }}
        </label>
        <div class="rating-filter">
          <RatingStars 
            :rating="filters.minRating"
            :size="20"
            @update:rating="onRatingChange"
          />
          <NcButton 
            v-if="filters.minRating > 0"
            variant="tertiary-no-background"
            :aria-label="t('memories', 'Clear rating filter')"
            @click="clearRating"
          >
            <template #icon>
              <CloseIcon :size="16" />
            </template>
          </NcButton>
        </div>
      </div>

      <!-- Embedded Tags Filter -->
      <div class="filter-section">
        <label class="filter-label">
          {{ t('memories', 'Filter by Embedded Tags') }}
        </label>
        <EmbeddedTagSelector
          :value="filters.embeddedTags"
          class="embedded-tags-filter"
          :disabled="disabled"
          :placeholder="t('memories', 'Select embedded tags...')"
          :show-full-path="true"
          :append-to-body="false"
          @update:value="onEmbeddedTagsChange"
        />
      </div>

      <!-- Filter Actions -->
      <div class="filter-actions">
        <NcButton
          variant="secondary"
          @click="clearAllFilters"
          :disabled="!hasActiveFilters"
        >
          {{ t('memories', 'Clear All') }}
        </NcButton>
      </div>
    </form>
  </div>
</template>

<script lang="ts">
import type { IFilters } from '@typings';

import NcButton from '@nextcloud/vue/components/NcButton';
import CloseIcon from 'vue-material-design-icons/Close.vue';
import { translate as t } from '@services/l10n';
import * as utils from '@services/utils';

import RatingStars from './RatingStars.vue';
import { defineComponent, type PropType } from 'vue';
import EmbeddedTagSelector from './EmbeddedTagSelector.vue';

export default defineComponent({
  name: 'FilterComponent',
  
  components: {
    NcButton,
    RatingStars,
    CloseIcon,
    EmbeddedTagSelector,
  },

  props: {
    /** Whether the component is disabled */
    disabled: {
      type: Boolean,
      default: false,
    },
    /** Initial filter values */
    initialFilters: {
      type: Object as PropType<IFilters>,
      default: () => ({
        minRating: 0,
        embeddedTags: [],
      } as IFilters),
    },
  },

  data: () => ({
    filters: {
      minRating: 0,
      embeddedTags: [],
    } as IFilters,
  }),

  computed: {
    hasActiveFilters() {
      const hasRatingFilter = this.filters.minRating > 0;
      const hasEmbeddedTagsFilter = this.filters.embeddedTags.length > 0;
      return hasRatingFilter || hasEmbeddedTagsFilter;
    },
  },

  watch: {
    initialFilters: {
      handler(newFilters) {
        this.filters = {
          minRating: newFilters.minRating || 0,
          embeddedTags: newFilters.embeddedTags || [],
        };
      },
      deep: true,
      immediate: true,
    },
  },

  methods: {
    emitFilterChange() {
      utils.bus.emit('memories:filters:changed', { ...this.filters });
    },

    onRatingChange(rating: number) {
      this.filters.minRating = rating;
      this.emitFilterChange();
    },

    onEmbeddedTagsChange(tags: string[]) {
      this.filters.embeddedTags = tags;
      this.emitFilterChange();
    },

    clearRating() {
      this.filters.minRating = 0;
      this.emitFilterChange();
    },

    clearAllFilters() {
      this.filters = {
        minRating: 0,
        embeddedTags: [],
      };
      this.emitFilterChange();
    },

    t(app: string, text: string, vars: Record<string, string>) {
      return t('memories', text, vars);
    },
  },
});
</script>

<style lang="scss" scoped>
.filter-component {
  padding: 16px;
  min-width: 300px;
  max-width: 400px;

  form {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
}

.filter-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-label {
  font-weight: 600;
  font-size: 14px;
  color: var(--color-text-darker);
}

.rating-filter {
  display: flex;
  align-items: center;
  gap: 8px;
}

.embedded-tags-filter {
  width: 100%;
}

.filter-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 8px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border);
}
</style>
