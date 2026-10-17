<template>
  <div v-if="windowDims.isMobile" class="mobile-actions">
    <FilterDropdownButton />
    <NcButton
      class="memories-menu-item search-menu"
      variant="tertiary-no-background"
      :title="t('memories', 'Search')"
      :aria-label="t('memories', 'Search')"
      @click="search"
    >
      <template #icon> <MagnifyIcon :size="20" /> </template>
    </NcButton>
  </div>

  <div v-else class="desktop-actions">
    <FilterDropdownButton />
    <Searchbar />
  </div>
</template>

<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton';

import Searchbar from '@components/header/Searchbar.vue';
import FilterDropdownButton from '@components/FilterDropdownButton.vue';

import { windowDims } from '@services/viewport';
import { t } from '@services/l10n';

import MagnifyIcon from 'vue-material-design-icons/Magnify.vue';

function search() {
  _m.modals.search();
}
</script>

<style lang="scss" scoped>
.mobile-actions,
.desktop-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.mobile-actions {
  flex-direction: row;
}

.desktop-actions {
  flex-direction: row;
}
</style>
