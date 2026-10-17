<template>
  <div class="metadata-overlay" v-if="showTags">
    <EmbeddedTagChips :tags="currentTags" :compact="true" :max-tags="3" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import { config } from '@services/user-config';
import * as utils from '@services/utils/common';

import EmbeddedTagChips from '@components/EmbeddedTagChips.vue';

import type { IPhoto } from '@typings';

const props = defineProps<{
  /** Photo to show the embedded tags of */
  data: IPhoto;
}>();

/** Get current photo embedded tags */
const currentTags = computed((): string[][] => {
  const exif = props.data.imageInfo?.exif || props.data.exif;
  return utils.getTagsFromExif(exif);
});

/** Whether to show the embedded tags overlay */
const showTags = computed((): boolean => {
  return config.metadata_in_gallery && currentTags.value.length > 0;
});
</script>

<style lang="scss" scoped>
.metadata-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 50;
  pointer-events: none;
  background: linear-gradient(180deg, transparent 0%, rgba(0, 0, 0, 0.7) 100%);

  // Compact styling for gallery
  :deep(.embedded-tag-chips) {
    font-size: 0.8em;
    gap: 2px;

    .more-tags {
      font-size: 0.7em;
    }
  }

  @media (max-width: 768px) {
    bottom: 2px;
    left: 2px;
    right: 2px;
    padding: 6px 4px 2px;

    :deep(.embedded-tag-chips) {
      font-size: 0.75em;
    }
  }
}
</style>
