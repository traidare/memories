<template>
  <div v-if="embeddedTags.length > 0 || canEdit" class="top-field top-field--embedded-tags">
    <div class="icon">
      <TagIcon :size="24" />
    </div>

    <div class="text">
      <div class="title">{{ t('memories', 'Tags') }} ({{ embeddedTags.length }})</div>
      <div class="tags-container" v-if="embeddedTags.length > 0">
        <template v-for="(tag, idx) in embeddedTags">
          <NcChip v-if="tag.length === 1" :key="`tag-${idx}`" :text="tag[0]" no-close />
          <div v-else-if="tag.length > 1" :key="`taglist-${idx}`" style="display: inline-block; margin: 2px">
            <NcPopover no-focus-trap>
              <template #trigger>
                <NcChip :text="tag[tag.length - 1]" no-close>
                  <template #icon>
                    <TreeIcon :size="16" />
                  </template>
                </NcChip>
              </template>
              <template #default>
                <div class="tag-path">{{ tag.join(' → ') }}</div>
              </template>
            </NcPopover>
          </div>
        </template>
      </div>
      <div v-else class="subtitle">
        {{ t('memories', 'Click edit to add tags') }}
      </div>
    </div>

    <div class="edit" v-if="canEdit">
      <NcActions :inline="1">
        <NcActionButton :aria-label="t('memories', 'Edit')" @click="editEmbeddedTags()">
          {{ t('memories', 'Edit') }}
          <template #icon> <EditIcon :size="20" /> </template>
        </NcActionButton>
      </NcActions>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import NcActions from '@nextcloud/vue/components/NcActions';
import NcActionButton from '@nextcloud/vue/components/NcActionButton';
import NcChip from '@nextcloud/vue/components/NcChip';
import NcPopover from '@nextcloud/vue/components/NcPopover';

import EditIcon from 'vue-material-design-icons/Pencil.vue';
import TagIcon from 'vue-material-design-icons/Tag.vue';
import TreeIcon from 'vue-material-design-icons/FileTree.vue';

import { t } from '@services/l10n';
import * as utils from '@services/utils/common';

import type { IExif } from '@typings';

const props = defineProps<{
  /** EXIF data of the current photo */
  exif: IExif;
  /** Whether the current user can edit the photo */
  canEdit?: boolean;
}>();

/** Embedded EXIF tags as hierarchical paths */
const embeddedTags = computed(() => utils.getTagsFromExif(props.exif));

/** Open the edit dialog on the embedded tags section */
function editEmbeddedTags() {
  _m.modals.editMetadata([_m.viewer.currentPhoto!], [6]);
}
</script>

<style lang="scss" scoped>
// The root gets the .top-field rules of Metadata.vue,
// but its scoped rules for nested elements don't reach here
.top-field {
  .icon,
  .edit {
    display: inline-block;
    margin-right: 10px;

    :deep(.material-design-icon) {
      color: var(--color-text-maxcontrast);
    }
  }
  .edit {
    transform: translateX(10px);
  }
  .text {
    display: inline-block;
    word-break: break-word;
    flex: 1;

    .subtitle {
      font-size: 0.95em;
    }
  }
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.tag-path {
  padding: 8px 12px;
  font-size: 0.9em;
  white-space: nowrap;
}
</style>
