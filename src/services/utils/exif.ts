import type { IExif } from '@typings';

/**
 * Extract tags from EXIF data
 * @param exif EXIF data object
 * @returns Array of tag arrays (hierarchical paths)
 */
export function getTagsFromExif(exif: any): string[][] {
  if (!exif) return [];

  // exiftool returns numeric tags such as years as numbers
  const ensureArray = (v: unknown): string[] => (v ? (Array.isArray(v) ? v : [v]) : []).map(String);

  const allTags: string[][] = [];
  const tagSet = new Set<string>();

  // Helper to add tags if not already present (with normalization for deduplication)
  const addTags = (tags: string[][]) => {
    for (const tag of tags) {
      const tagPath = Array.isArray(tag) ? tag : [tag];
      // Normalize to '/' separator for deduplication key
      const normalizedKey = tagPath.join('/').toLowerCase();
      if (!tagSet.has(normalizedKey)) {
        tagSet.add(normalizedKey);
        allTags.push(tagPath);
      }
    }
  };

  // Extract from TagsList (split by '/')
  const tagsList = ensureArray(exif.TagsList).map((tag) => tag.split('/'));
  addTags(tagsList);

  // Extract from HierarchicalSubject (split by '|')
  const hierarchicalSubject = ensureArray(exif.HierarchicalSubject).map((tag) => tag.split('|'));
  addTags(hierarchicalSubject);

  // Extract from Keywords (as individual tags)
  const keywords = ensureArray(exif.Keywords).map((tag) => {
    // Keywords might contain paths with '/' or '|' separator
    return tag.includes('/') ? tag.split('/') : tag.includes('|') ? tag.split('|') : [tag];
  });
  addTags(keywords);

  // Extract from Subject (as individual tags)
  const subject = ensureArray(exif.Subject).map((tag) => [tag]);
  addTags(subject);

  // Filter out tags that are components of hierarchical tags
  return filterComponentTags(allTags);
}

/**
 * Convert tags to EXIF data, the reverse of getTagsFromExif
 * @param tags Tag paths separated by '/' or '|'
 * @returns All four tag fields, which are deleted if there are no tags
 */
export function getExifFromTags(
  tags: string[],
): Pick<IExif, 'Keywords' | 'Subject' | 'TagsList' | 'HierarchicalSubject'> {
  // Return the tags in all four EXIF fields for maximum compatibility
  const tagsList = tags.map((tag) => tag.replace(/\|/g, '/'));
  const hierarchicalSubject = tags.map((tag) => tag.replace(/\//g, '|'));
  const keywords = tags.map((tag) => tag.replace(/\|/g, '/'));
  const subject = tags.map((tag) => {
    const parts = tag.split(/[\/|]/);
    return parts[parts.length - 1];
  });

  return {
    Keywords: keywords,
    Subject: subject,
    TagsList: tagsList,
    HierarchicalSubject: hierarchicalSubject,
  };
}

/**
 * Filter out tags that are components of hierarchical tags
 * For example, if we have "Country/Italy", don't also show "Country" or "Italy"
 */
function filterComponentTags(tags: string[][]): string[][] {
  if (tags.length === 0) return tags;

  // Step 1: Collect all tags into normalized collections
  const flatTags = new Set<string>(); // Single-part tags (no separator)
  const hierarchicalTags: string[][] = []; // Multi-part tags (length > 1)
  const hierarchicalTagParts = new Set<string>(); // All parts from hierarchical tags

  for (const tag of tags) {
    const normalized = tag.map((part) => part.toLowerCase());

    if (normalized.length === 1) {
      // Single-part tag
      flatTags.add(normalized[0]);
    } else {
      // Multi-part hierarchical tag
      hierarchicalTags.push(tag);

      // Add all parts to hierarchicalTagParts
      for (const part of normalized) {
        hierarchicalTagParts.add(part);
      }
    }
  }

  // Step 2: Combination - remove flat tags that are parts of hierarchical tags
  const result: string[][] = [];

  // Add flat tags that are NOT components of hierarchical tags
  for (const flatTag of flatTags) {
    if (!hierarchicalTagParts.has(flatTag)) {
      // Find the original case from the input tags
      const originalTag = tags.find((t) => t.length === 1 && t[0].toLowerCase() === flatTag);
      if (originalTag) {
        result.push(originalTag);
      }
    }
  }

  // Add all hierarchical tags
  result.push(...hierarchicalTags);

  return result;
}
