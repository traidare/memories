<template>
  <div class="filter-component" role="dialog" :aria-label="t('memories', 'Filter by Embedded Tags')">
    <h2>{{ t('memories', 'Embedded tags') }}</h2>

    <NcTextField
      ref="searchField"
      v-model="search"
      type="search"
      :label="t('memories', 'Search embedded tags')"
      :placeholder="t('memories', 'Search tags…')"
      :show-trailing-button="!!search"
      :disabled="disabled"
      autocomplete="off"
      @trailing-button-click="search = ''"
    >
      <template #icon><MagnifyIcon :size="20" /></template>
    </NcTextField>

    <div v-if="selectedTags.length" class="selected-tags" :aria-label="t('memories', 'Selected tags')" role="group">
      <NcChip
        v-for="tag in selectedTags"
        :key="tag"
        :text="tag"
        :title="tag"
        :aria-label-close="t('memories', 'Remove {tag} filter', { tag })"
        :no-close="disabled"
        @close="removeTag(tag)"
      />
    </div>

    <div
      v-if="loading || loadFailed || !allTags.length || matchingTags.length || search.trim()"
      class="filter-options"
      :aria-busy="loading"
    >
      <div v-if="loading" class="filter-status" role="status">
        <NcLoadingIcon :size="24" />
        {{ t('memories', 'Loading tags…') }}
      </div>
      <div v-else-if="loadFailed" class="filter-status" role="alert">
        <p>{{ t('memories', 'Could not load embedded tags.') }}</p>
        <NcButton :disabled="disabled" @click="loadTags">{{ t('memories', 'Try again') }}</NcButton>
      </div>
      <p v-else-if="!allTags.length" class="filter-status" role="status">
        {{ t('memories', 'No embedded tags found.') }}
      </p>
      <p v-else-if="!matchingTags.length" class="filter-status" role="status">
        {{ t('memories', 'No tags match your search.') }}
      </p>
      <div v-else class="tag-list" role="group" :aria-label="t('memories', 'Embedded tags')">
        <button
          v-for="tag in matchingTags"
          :key="tag"
          type="button"
          :aria-label="t('memories', 'Add {tag} filter', { tag })"
          :title="tag"
          :disabled="disabled"
          class="tag-option"
          @click="addTag(tag)"
        >
          <span class="tag-label">
            <span class="tag-name">{{ tag.split('/').at(-1) }}</span>
            <span v-if="tag.includes('/')" class="tag-path">{{ tag.split('/').slice(0, -1).join(' → ') }}</span>
          </span>
        </button>
      </div>
    </div>

    <NcButton
      v-if="selectedTags.length"
      class="clear-filters"
      variant="tertiary"
      :disabled="disabled"
      @click="selectedTags = []"
    >
      {{ t('memories', 'Clear all') }}
    </NcButton>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef } from 'vue';
import axios from '@nextcloud/axios';
import NcButton from '@nextcloud/vue/components/NcButton';
import NcChip from '@nextcloud/vue/components/NcChip';
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon';
import NcTextField from '@nextcloud/vue/components/NcTextField';
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue';

import { API } from '@services/API';
import { t } from '@services/l10n';
import { bus } from '@services/utils/event-bus';
import type { IEmbeddedTagsResponse, IFilters } from '@typings';

const props = withDefaults(defineProps<{ disabled?: boolean; initialFilters: IFilters }>(), { disabled: false });

const searchField = useTemplateRef('searchField');
const search = ref('');
const allTags = ref<string[]>([]);
const loading = ref(true);
const loadFailed = ref(false);

const selectedTags = computed({
  get: () => props.initialFilters.embeddedTags,
  set: (embeddedTags: string[]) => bus.emit('memories:filters:changed', { embeddedTags }),
});

const matchingTags = computed(() => {
  const query = search.value.trim().toLocaleLowerCase();
  return allTags.value.filter((tag) => !selectedTags.value.includes(tag) && tag.toLocaleLowerCase().includes(query));
});

onMounted(loadTags);

async function loadTags() {
  loading.value = true;
  loadFailed.value = false;
  try {
    const { data } = await axios.get<IEmbeddedTagsResponse>(API.EMBEDDED_TAGS_FLAT());
    allTags.value = [...new Set(data.tags.map((tag) => tag.path))];
  } catch (error) {
    console.error('Failed to load embedded tags:', error);
    loadFailed.value = true;
  } finally {
    loading.value = false;
  }
}

function addTag(tag: string) {
  selectedTags.value = [...selectedTags.value, tag];
  search.value = '';
  searchField.value?.focus();
}

function removeTag(tag: string) {
  selectedTags.value = selectedTags.value.filter((selected) => selected !== tag);
  searchField.value?.focus();
}
</script>

<style lang="scss" scoped>
.filter-component {
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: min(360px, calc(100vw - 24px));
  max-height: calc(100dvh - 72px);
  padding: 16px;
  overflow: auto;

  > :not(.filter-options) {
    flex-shrink: 0;
  }
}

h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
}

.selected-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  max-height: 96px;
  overflow: auto;

  :deep(.nc-chip) {
    max-width: 100%;
  }
}

.filter-options {
  min-height: 0;
  max-height: 280px;
  overflow: auto;
  overscroll-behavior: contain;
}

.tag-list {
  display: flex;
  flex-direction: column;
}

.tag-option {
  width: 100%;
  min-height: 44px;
  margin: 0;
  padding: 8px;
  border: 0;
  border-radius: var(--border-radius);
  background: transparent;
  color: var(--color-main-text);
  font: inherit;
  text-align: start;

  &:hover,
  &:focus-visible {
    background: var(--color-background-hover);
  }
}

.tag-label {
  display: flex;
  flex-direction: column;
  overflow-wrap: anywhere;
}

.tag-path {
  font-size: 12px;
  color: var(--color-text-lighter);
}

.filter-status {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 24px 8px;
  text-align: center;
  color: var(--color-text-lighter);
}

.clear-filters {
  align-self: flex-end;
}
</style>
