import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const readJson = async (path) => JSON.parse(await readFile(new URL(path, import.meta.url), 'utf8'));
const root = new URL('../', import.meta.url);
const read = async (path) => readFile(new URL(path, root), 'utf8');

const destinationSchema = await readJson('../strapi/src/api/destination/content-types/destination/schema.json');
const settingsSchema = await readJson('../strapi/src/api/site-setting/content-types/site-setting/schema.json');
const demo = await readJson('../data/demo-content.json');
const manifest = await readJson('../strapi/package.json');
const php = await read('wordpress/wp-content/plugins/yemen-travel-site/yemen-travel-site.php');
const compose = await read('docker-compose.yml');
const laragonSetup = await read('scripts/install-laragon.ps1');
const laragonStart = await read('scripts/start-laragon-strapi.ps1');

assert.equal(destinationSchema.info.pluralName, 'destinations');
assert.equal(settingsSchema.kind, 'singleType');
assert.equal(manifest.dependencies['@strapi/strapi'], '5.62.0');
assert.equal(manifest.dependencies['@strapi/upload'], '5.62.0');
assert.equal(manifest.dependencies.mysql2, '3.24.6');
assert.match(php, /add_shortcode\('yemen_travel_home'/);
assert.match(php, /add_shortcode\('yemen_destinations'/);
assert.ok(php.includes('YTS_ALLOW_LARAGON_STRAPI'));
assert.match(php, /site-setting\?populate=\*/);
assert.match(php, /destinations\?sort=sortOrder:asc/);
assert.match(compose, /wordpress-db:/);
assert.match(compose, /strapi-db:/);
assert.match(laragonSetup, /yemen_wordpress/);
assert.match(laragonSetup, /yemen_strapi/);
assert.match(laragonStart, /npm run develop/);
assert.equal(demo.destinations.length, 4);
assert.ok(demo.destinations.every((item) => item.imageUrl.startsWith('https://') && item.imageCredit && item.imageLicenseUrl));
assert.ok(demo.destinations.every((item) => item.heritageUrl.startsWith('https://')));

console.log('Project structure, Strapi schemas, WordPress API paths, and demo content: OK');

