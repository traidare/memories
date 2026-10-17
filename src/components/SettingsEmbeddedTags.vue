<template>
  <NcCheckboxRadioSwitch
    :model-value="config.metadata_in_gallery"
    @update:model-value="updateMetadataInGallery"
    type="switch"
  >
    {{ t('memories', 'Show metadata in gallery') }}
  </NcCheckboxRadioSwitch>
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue';

import { config, setConfig } from '@services/user-config';
import { t } from '@services/l10n';

const NcCheckboxRadioSwitch = defineAsyncComponent(() => import('@nextcloud/vue/components/NcCheckboxRadioSwitch'));

async function updateMetadataInGallery(val: boolean) {
  await setConfig('metadata_in_gallery', val);
}
</script>
