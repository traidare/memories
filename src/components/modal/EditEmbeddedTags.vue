<template>
  <div class="outer">
    <!-- Single photo editing -->
    <div v-if="photos.length === 1">
      <EmbeddedTagSelector
        ref="embeddedTagSelector"
        :value="tagSelection"
        @update:value="tagSelection = $event"
        :disabled="disabled"
        :multiple="true"
        :placeholder="t('memories', 'Select tags...')"
        :show-full-path="true"
      />
    </div>

    <!-- Multiple photos editing -->
    <EditEmbeddedTagsMulti v-else ref="editEmbeddedTagsMulti" :photos="photos" :disabled="disabled" />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

import EmbeddedTagSelector from '@components/EmbeddedTagSelector.vue';
import EditEmbeddedTagsMulti from './EditEmbeddedTagsMulti.vue';

import * as utils from '@services/utils/common';
import { translate as t } from '@services/l10n';

import type { IExif, IPhoto } from '@typings';

export default defineComponent({
  name: 'EditEmbeddedTags',
  components: {
    EmbeddedTagSelector,
    EditEmbeddedTagsMulti,
  },

  props: {
    photos: {
      type: Array<IPhoto>,
      required: true,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
  },

  data: () => ({
    tagSelection: [] as string[],
  }),

  mounted() {
    this.init();
  },

  methods: {
    t,

    init() {
      // Only init for single photo (multi-photo component handles its own init)
      if (this.photos.length === 1) {
        const photo = this.photos[0];
        const exif = photo.imageInfo?.exif;

        if (exif) {
          const tags = utils.getTagsFromExif(exif);
          this.tagSelection = tags.map((tagPath) => tagPath.join('/'));
        }
      }
    },

    /** EXIF tag fields to save for a photo, or null if its tags don't change */
    exifFor(photo: IPhoto): Partial<IExif> | null {
      const exif = photo.imageInfo?.exif;
      const currentTags = exif ? utils.getTagsFromExif(exif).map((t) => t.join('/')) : [];
      let newTags: string[];

      if (this.photos.length === 1) {
        // Single photo: replace all tags, sorted
        newTags = [...this.tagSelection].sort();
      } else {
        // Multiple photos: add, remove, or override
        const multi = this.$refs.editEmbeddedTagsMulti as InstanceType<typeof EditEmbeddedTagsMulti> | undefined;
        const operation = multi?.result();
        if (!operation) return null;

        if (operation.mode === 'add') {
          // Add tags: merge with existing
          newTags = [...new Set([...currentTags, ...operation.tags])];
        } else if (operation.mode === 'remove') {
          // Remove tags: filter out specified tags
          const tagsToRemove = new Set(operation.tags);
          newTags = currentTags.filter((t) => !tagsToRemove.has(t));
        } else {
          // Override: replace all tags
          newTags = operation.tags;
        }
      }

      // Skip photos whose tags didn't change
      if (JSON.stringify([...newTags].sort()) === JSON.stringify([...currentTags].sort())) {
        return null;
      }

      return utils.getExifFromTags(newTags);
    },
  },
});
</script>

<style scoped lang="scss">
.outer {
  margin-top: 10px;

  :deep(.embedded-tag-selector) {
    width: 100%;
  }
}
</style>
