import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { once } from 'node:events';
import test from 'node:test';

const readSource = (path) => readFile(new URL(path, import.meta.url), 'utf8');

const getAvailablePort = () => new Promise((resolve, reject) => {
  const socket = createServer();
  socket.once('error', reject);
  socket.listen(0, '127.0.0.1', () => {
    const { port } = socket.address();
    socket.close((error) => error ? reject(error) : resolve(port));
  });
});

const waitForPage = async (url) => {
  const deadline = Date.now() + 30_000;

  while (Date.now() < deadline) {
    try {
      const response = await fetch(url);
      if (response.ok) {
        return response.text();
      }
    } catch {
      // The development server is still starting.
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  throw new Error(`Timed out waiting for ${url}`);
};

test('EnterCheck submit navigation reaches the Map page', { timeout: 45_000 }, async () => {
  const [enterCheck, mapPage, map] = await Promise.all([
    readSource('../src/components/EnterCheck/index.tsx'),
    readSource('../src/pages/search-result.tsx'),
    readSource('../src/components/SearchResult/index.tsx'),
  ]);

  assert.match(enterCheck, /<form onSubmit=\{handleNext\}>/);
  assert.match(enterCheck, /event\.preventDefault\(\)/);
  assert.match(enterCheck, /router\.push\('\/search-result'\)/);
  assert.match(enterCheck, /type="submit"/);
  assert.match(mapPage, /<SearchResult \/>/);
  assert.match(map, /mainRef\.current\?\.focus\(\)/);
  assert.match(map, /<MunicipalityMap/);
  assert.match(map, /aria-labelledby="legend-heading"/);
  for (const color of ['#2A78D6', '#EDA100', '#D03B3B', '#C3C2B7', '#898781']) {
    assert.match(map, new RegExp(color));
  }

  const port = await getAvailablePort();
  const frontendDirectory = new URL('../', import.meta.url);
  const next = new URL('../node_modules/next/dist/bin/next', import.meta.url);
  const server = spawn(process.execPath, [next.pathname, 'dev', '--port', String(port)], {
    cwd: frontendDirectory,
    env: { ...process.env, NEXT_TELEMETRY_DISABLED: '1' },
    stdio: 'ignore',
  });

  try {
    // budgetはJotaiのatomWithStorage（sessionStorage）経由で渡すため、クエリパラメータでは
    // 渡らない（bird_design.md §7.1）。fetch()単体ではJSが実行されずbudget未設定時の
    // リダイレクトも発生しないため、ここでは静的なマークアップの存在のみ確認する。
    const [enterCheckPage, mapPageHtml] = await Promise.all([
      waitForPage(`http://127.0.0.1:${port}/enter-check`),
      waitForPage(`http://127.0.0.1:${port}/search-result`),
    ]);

    assert.match(enterCheckPage, /予算確認画面/);
    assert.match(enterCheckPage, /次へ/);
    assert.match(mapPageHtml, /地図画面/);
    assert.match(mapPageHtml, /予算から1,000万円以内/);
  } finally {
    server.kill('SIGTERM');
    await once(server, 'exit');
  }
});
