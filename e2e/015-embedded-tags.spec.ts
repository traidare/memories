import { test, expect } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { createCanvas } from '@napi-rs/canvas';
import { appUrl, username, e2eHeaders, bootstrap, teardown, psub } from './navigation';
import { DavClient } from './utils';

test.beforeEach(bootstrap);
test.afterEach(teardown);

async function openFilter(page: Page) {
  await page.goto(appUrl);
  await page.getByRole('button', { name: 'Filter photos', exact: true }).click();
  const panel = page.getByRole('dialog', { name: 'Filter by Embedded Tags' });
  await expect(panel).toBeVisible();
  return panel;
}

function tagOption(panel: Locator, tag: string) {
  return panel.getByRole('button', { name: `Add ${tag} filter`, exact: true });
}

function selectedTag(panel: Locator, tag: string) {
  return panel.getByRole('button', { name: `Remove ${tag} filter`, exact: true });
}

async function closeFilter(panel: Locator) {
  await panel.getByRole('searchbox').press('Escape');
  await expect(panel).toBeHidden();
}

test.describe('Embedded tag filters', () => {
  const folder = psub('/for-embedded-tags-%wid');
  const travel = psub('e2e-travel-%wid');
  const vacation = psub('e2e-vacation-%wid');
  const city = psub('City, Night %wid');
  const literal = psub('100%_! %wid');

  test.use({ extraHTTPHeaders: e2eHeaders({ timelinePath: '/for-embedded-tags-%wid' }) });

  test.beforeAll(async ({ request }) => {
    const dav = new DavClient(request);
    await dav.deleteFile(folder, true);
    await dav.mkcol(`files/${username}${folder}`);
    const canvas = createCanvas(120, 120);
    const image = canvas.toBuffer('image/jpeg');

    for (const [name, tags] of [
      ['beach', [`${travel}/Beach`, vacation]],
      ['mountain', [`${travel}/Mountain`, vacation]],
      ['city', [city, literal]],
    ] as const) {
      await dav.putFile(`${folder}/${name}.jpg`, image, 'image/jpeg');
      const fileid = await dav.fileid(`${folder}/${name}.jpg`);
      const response = await request.patch(`${appUrl}/api/image/set-exif/${fileid}`, {
        data: {
          raw: {
            Keywords: tags,
            Subject: tags.map((tag) => tag.split('/').at(-1)),
            TagsList: tags,
            HierarchicalSubject: tags.map((tag) => tag.replaceAll('/', '|')),
          },
        },
      });
      expect(response.ok(), await response.text()).toBeTruthy();
    }
  });

  test.afterAll(async ({ request }) => {
    await new DavClient(request).deleteFile(folder, true);
  });

  test('@ui Parent tags include subtags and multiple selections match all tags', async ({ page }) => {
    const panel = await openFilter(page);
    await expect(page.locator('.p-outer:visible')).toHaveCount(3);

    await tagOption(panel, travel).click();
    await expect(page.locator('.p-outer:visible')).toHaveCount(2);
    await tagOption(panel, vacation).click();
    await expect(page.locator('.p-outer:visible')).toHaveCount(2);
    await tagOption(panel, `${travel}/Beach`).click();
    await expect(page.locator('.p-outer:visible')).toHaveCount(1);
    await expect(panel).toBeVisible();
    await expect(panel.getByRole('group', { name: 'Selected tags' }).getByRole('button')).toHaveCount(3);
    await expect(tagOption(panel, travel)).toHaveCount(0);

    await panel.getByRole('button', { name: 'Clear all', exact: true }).click();
    await expect(page.locator('.p-outer:visible')).toHaveCount(3);
    await expect(panel.getByRole('group', { name: 'Selected tags' })).toHaveCount(0);
    await expect(panel.getByRole('button', { name: 'Clear all', exact: true })).toHaveCount(0);
    await expect(tagOption(panel, travel)).toBeVisible();
  });

  test('@ui Search keeps selections visible and cannot create nonexistent tags', async ({ page }) => {
    const panel = await openFilter(page);
    await tagOption(panel, travel).click();
    const search = panel.getByRole('searchbox', { name: 'Search embedded tags' });
    await search.fill(`  ${travel.toUpperCase()}/BEACH  `);
    await expect(panel.getByRole('group', { name: 'Embedded tags' }).getByRole('button')).toHaveCount(1);
    await expect(tagOption(panel, `${travel}/Beach`)).toBeVisible();
    const remove = selectedTag(panel, travel);
    await expect(remove).toBeVisible();

    await search.fill('a tag that does not exist');
    await search.press('Enter');
    await expect(panel.getByText('No tags match your search.')).toBeVisible();
    await expect(panel.getByRole('group', { name: 'Selected tags' }).getByRole('button')).toHaveCount(1);
    await expect(page.locator('.p-outer:visible')).toHaveCount(2);
    await remove.click();
    await expect(search).toBeFocused();
    await expect(page.locator('.p-outer:visible')).toHaveCount(3);
  });

  test('@ui Keyboard selection and dismissal preserve focus and filters', async ({ page }) => {
    const panel = await openFilter(page);
    const search = panel.getByRole('searchbox');
    await expect(search).toBeFocused();
    await search.fill(vacation);
    await search.press('Tab');
    const clearSearch = panel.getByRole('button', { name: 'Clear text', exact: true });
    await expect(clearSearch).toBeFocused();
    await clearSearch.press('Tab');
    const option = tagOption(panel, vacation);
    await expect(option).toBeFocused();
    await option.press('Enter');
    await expect(selectedTag(panel, vacation)).toBeVisible();
    await expect(option).toHaveCount(0);
    await expect(search).toHaveValue('');
    await expect(search).toBeFocused();
    await expect(page.locator('.p-outer:visible')).toHaveCount(2);
    await closeFilter(panel);
    const trigger = page.getByRole('button', { name: 'Filter photos', exact: true });
    await expect(trigger).toBeFocused();

    await trigger.press('Enter');
    await expect(selectedTag(panel, vacation)).toBeVisible();
    await trigger.click();
    await expect(panel).toBeHidden();
    await expect(trigger).toBeFocused();
    await trigger.click();
    await page.locator('body').click({ position: { x: 10, y: 300 } });
    await expect(panel).toBeHidden();
    await expect(trigger).toBeVisible();
  });

  test('@ui Empty timeline reset also clears the menu', async ({ page }) => {
    const panel = await openFilter(page);
    await tagOption(panel, travel).click();
    await tagOption(panel, city).click();
    await expect(page.locator('.p-outer:visible')).toHaveCount(0);
    await closeFilter(panel);
    await page.getByRole('button', { name: 'Reset filters', exact: true }).click();
    await expect(page.locator('.p-outer:visible')).toHaveCount(3);
    await page.getByRole('button', { name: 'Filter photos', exact: true }).click();
    await expect(panel.getByRole('group', { name: 'Selected tags' })).toHaveCount(0);
  });

  test('@ui Commas and percent characters filter photos literally', async ({ page }) => {
    const panel = await openFilter(page);
    await tagOption(panel, city).click();
    await tagOption(panel, literal).click();
    await expect(page.locator('.p-outer:visible')).toHaveCount(1);
    await expect(panel.getByRole('group', { name: 'Selected tags' }).getByRole('button')).toHaveCount(2);
  });

  test('@ui Active filters survive switching between desktop and mobile layouts', async ({ page }) => {
    const panel = await openFilter(page);
    await tagOption(panel, travel).click();
    await closeFilter(panel);
    for (const viewport of [
      { width: 375, height: 667 },
      { width: 1280, height: 720 },
    ]) {
      await page.setViewportSize(viewport);
      await expect(page.locator(viewport.width === 375 ? '.mobile-actions' : '.desktop-actions')).toBeVisible();
      await page.getByRole('button', { name: 'Filter photos', exact: true }).click();
      await expect(selectedTag(panel, travel)).toBeVisible();
      await expect(page.locator('.p-outer:visible')).toHaveCount(2);
      await closeFilter(panel);
    }
  });

  test('@ui Filters selected outside the timeline apply when returning to it', async ({ page }) => {
    await page.goto(appUrl);
    await page.getByRole('link', { name: 'Explore', exact: true }).click();
    await page.getByRole('button', { name: 'Filter photos', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Filter by Embedded Tags' });
    await tagOption(panel, travel).click();
    await closeFilter(panel);
    await page.getByRole('link', { name: 'Timeline', exact: true }).click();
    await expect(page.locator('.p-outer:visible')).toHaveCount(2);
    await page.getByRole('button', { name: 'Filter photos', exact: true }).click();
    await expect(selectedTag(panel, travel)).toBeVisible();
  });
});

test.describe('Embedded tag menu states', () => {
  test('Allows dismissal while loading and shows an empty catalog', async ({ page }) => {
    let release!: () => void;
    const loading = new Promise<void>((resolve) => {
      release = resolve;
    });
    await page.route('**/api/embedded-tags/flat', async (route) => {
      await loading;
      await route.fulfill({ json: { tags: [] } });
    });
    const panel = await openFilter(page);
    try {
      await expect(panel.getByText('Loading tags…')).toBeVisible();
      await closeFilter(panel);
      await page.getByRole('button', { name: 'Filter photos', exact: true }).click();
      await expect(panel.getByText('Loading tags…')).toBeVisible();
    } finally {
      release();
    }
    await expect(panel.getByText('No embedded tags found.')).toBeVisible();
    await expect(panel.getByRole('button', { name: 'Clear all', exact: true })).toHaveCount(0);
  });

  test('Distinguishes a failed catalog from an empty catalog and can retry', async ({ page }) => {
    let attempts = 0;
    await page.route('**/api/embedded-tags/flat', (route) =>
      ++attempts === 1
        ? route.fulfill({ status: 503, json: {} })
        : route.fulfill({ json: { tags: [{ path: 'Travel' }] } }),
    );
    const panel = await openFilter(page);
    await expect(panel.getByRole('alert')).toContainText('Could not load embedded tags.');
    await panel.getByRole('button', { name: 'Try again', exact: true }).click();
    await expect(tagOption(panel, 'Travel')).toBeVisible();
    await expect(panel.getByRole('alert')).toHaveCount(0);
    expect(attempts).toBe(2);
  });

  test('Selected tags appear only as removable selections', async ({ page }) => {
    await page.route('**/api/embedded-tags/flat', (route) =>
      route.fulfill({ json: { tags: [{ path: 'Travel' }, { path: 'Vacation' }] } }),
    );
    const panel = await openFilter(page);
    await tagOption(panel, 'Travel').click();
    await expect(tagOption(panel, 'Travel')).toHaveCount(0);
    await tagOption(panel, 'Vacation').click();
    await expect(panel.getByRole('group', { name: 'Selected tags' }).getByRole('button')).toHaveCount(2);
    await expect(panel.getByRole('group', { name: 'Embedded tags' })).toHaveCount(0);
    await expect(panel.getByText('No tags match your search.')).toHaveCount(0);
    await selectedTag(panel, 'Travel').click();
    await expect(tagOption(panel, 'Travel')).toBeVisible();
    await expect(selectedTag(panel, 'Vacation')).toBeVisible();
  });

  for (const viewport of [
    { width: 320, height: 568 },
    { width: 375, height: 667 },
    { width: 844, height: 390 },
  ]) {
    test(`@ui Long paths and many tags fit ${viewport.width}×${viewport.height}`, async ({ page }) => {
      await page.setViewportSize(viewport);
      const longPath = `Travel/${'LongUnbrokenTagName'.repeat(12)}`;
      const tags = [longPath, ...Array.from({ length: 100 }, (_, index) => `Travel/Tag ${index}`)];
      await page.route('**/api/embedded-tags/flat', (route) =>
        route.fulfill({ json: { tags: tags.map((path) => ({ path })) } }),
      );
      const panel = await openFilter(page);
      await panel.getByRole('searchbox').fill('LongUnbrokenTagName');
      await tagOption(panel, longPath).click();
      await panel.getByRole('searchbox').fill('');
      await expect(panel.getByRole('group', { name: 'Embedded tags' }).getByRole('button')).toHaveCount(100);

      const bounds = await panel.boundingBox();
      expect(bounds).not.toBeNull();
      expect(bounds!.x).toBeGreaterThanOrEqual(0);
      expect(bounds!.y).toBeGreaterThanOrEqual(0);
      expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(viewport.width);
      expect(bounds!.y + bounds!.height).toBeLessThanOrEqual(viewport.height);
      expect(await panel.evaluate((el) => el.scrollWidth <= el.clientWidth)).toBeTruthy();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
      await panel.getByRole('button', { name: 'Clear all', exact: true }).click();
      await expect(panel.getByRole('group', { name: 'Selected tags' })).toHaveCount(0);
      await closeFilter(panel);
    });
  }
});
