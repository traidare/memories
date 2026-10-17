<template>
  <div class="embedded-tag-selector" @mousedown.capture="keepFocusOnDeselect">
    <NcSelect
      :no-wrap="noWrap"
      v-model="selectedTags"
      :options="allTags"
      :multiple="multiple"
      :loading="loading"
      :input-label="inputLabel"
      :placeholder="placeholder"
      :keep-open="true"
      :disabled="disabled"
      :append-to-body="appendToBody"
      :taggable="true"
      @option:created="handleCreate"
    />
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import NcSelect from '@nextcloud/vue/components/NcSelect';
import axios from '@nextcloud/axios';
import { API } from '@services/API';

export default defineComponent({
  name: 'EmbeddedTagSelector',

  components: {
    NcSelect,
  },

  props: {
    /** Selected tag values */
    value: {
      type: Array as PropType<string[]>,
      default: () => [],
    },

    /** Whether to allow multiple selections */
    multiple: {
      type: Boolean,
      default: true,
    },

    /** Input label */
    inputLabel: {
      type: String,
      default: 'Select Tags',
    },

    /** Placeholder text */
    placeholder: {
      type: String,
      default: 'Search for tags...',
    },

    /** Whether to wrap selected items */
    noWrap: {
      type: Boolean,
      default: false,
    },

    /** Disabled state */
    disabled: {
      type: Boolean,
      default: false,
    },

    /** Whether to show full path instead of just tag name */
    showFullPath: {
      type: Boolean,
      default: false,
    },

    /** Render the options list in <body> instead of below the input */
    appendToBody: {
      type: Boolean,
      default: true,
    },
  },

  emits: {
    'update:value': (value: string[]) => true,
  },

  data() {
    return {
      allTags: [] as string[],
      selectedTags: [] as string[],
      loading: false,
    };
  },

  watch: {
    value: {
      immediate: true,
      handler(newValue: string[]) {
        this.selectedTags = newValue || [];
      },
    },

    selectedTags(newSelection: string[]) {
      // Don't echo a selection that came from the parent
      if (newSelection !== this.value) {
        this.$emit('update:value', newSelection);
      }
    },
  },

  async mounted() {
    await this.loadTags();
  },

  methods: {
    async loadTags() {
      this.loading = true;
      try {
        const response = await axios.get<{ tags?: { tag: string; path: string }[] }>(API.EMBEDDED_TAGS_FLAT());
        // Transform tags to simple strings for NcSelect options
        this.allTags = (response.data.tags || []).map((tagObj) => (this.showFullPath ? tagObj.path : tagObj.tag));
      } catch (error) {
        console.error('Failed to load embedded tags:', error);
        this.allTags = [];
      } finally {
        this.loading = false;
      }
    },

    keepFocusOnDeselect(event: MouseEvent) {
      // Pressing a tag's remove button would focus it, and the search input
      // losing focus closes the options list. Keep the focus where it is.
      if ((event.target as Element).closest('.vs__deselect')) {
        event.preventDefault();
      }
    },

    handleCreate(newTag: string) {
      // Add the newly created tag to the options list
      if (!this.allTags.includes(newTag)) {
        this.allTags.push(newTag);
      }
    },
  },
});
</script>

<style lang="scss" scoped>
.embedded-tag-selector {
  width: 100%;

  :deep(.vs__dropdown-menu) {
    max-height: 200px;
  }

  :deep(.v-select) {
    width: 100%;
  }
}
</style>
