<template>
  <div class="bottom-bar-right" v-if="config.metadata_in_slideshow && currentTags.length > 0">
    <EmbeddedTagChips :tags="currentTags" :slideshow="true" :compact="true" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import { config } from '@services/user-config';
import * as utils from '@services/utils/common';

import EmbeddedTagChips from '@components/EmbeddedTagChips.vue';

import type { IPhoto } from '@typings';

const props = withDefaults(
  defineProps<{
    /** Photo shown in the viewer */
    photo?: IPhoto | null;
  }>(),
  {
    photo: null,
  },
);

/** Get current photo embedded tags */
const currentTags = computed((): string[][] => {
  const exif = props.photo?.imageInfo?.exif;
  return utils.getTagsFromExif(exif);
});
</script>

<style lang="scss" scoped>
.bottom-bar-right {
  flex-shrink: 0;
  margin-left: 16px;
  max-width: 50%;

  @media (max-width: 768px) {
    display: none;
  }
}
</style>
