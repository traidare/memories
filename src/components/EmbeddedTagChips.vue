<template>
  <div class="embedded-tag-chips" :class="{ compact, slideshow }">
    <NcChip v-for="(tag, idx) in tags.slice(0, maxTags)" :key="`tag-${idx}`" :text="truncateTagPath(tag)" no-close />

    <span v-if="tags.length > maxTags" class="more-tags"> +{{ tags.length - maxTags }} </span>
  </div>
</template>

<script setup lang="ts">
import NcChip from '@nextcloud/vue/components/NcChip';

withDefaults(
  defineProps<{
    /** Hierarchical tags as lists of path parts */
    tags: string[][];
    /** Compact display mode */
    compact?: boolean;
    /** Slideshow mode styling */
    slideshow?: boolean;
    /** Maximum number of tags to show before truncating */
    maxTags?: number;
  }>(),
  {
    compact: false,
    slideshow: false,
    maxTags: 5,
  },
);

/** Join the tag path, keeping only its end if it is too long */
function truncateTagPath(tag: string[]): string {
  const path = tag.join(' → ');
  return path.length <= 200 ? path : '…' + path.substring(path.length - 150);
}
</script>

<style lang="scss" scoped>
.embedded-tag-chips {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
  min-width: 0; // Allow flex shrinking

  &.compact {
    font-size: 0.9em;
  }

  &.slideshow {
    color: white;

    .more-tags {
      color: rgba(255, 255, 255, 0.8);
    }
  }
}

.more-tags {
  font-size: 0.85em;
  color: var(--color-text-lighter);
  margin-left: 4px;
  white-space: nowrap;
}

@media (max-width: 768px) {
  .embedded-tag-chips {
    gap: 2px;

    &:not(.compact) {
      font-size: 0.9em;
    }
  }
}
</style>
