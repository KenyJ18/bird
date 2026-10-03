// maplibre-gl は自身のWorkerスクリプトを `new URL('./maplibre-gl-worker.mjs', import.meta.url)`
// で解決するが、Turbopackはこの参照先ファイル自体はコピー・ハッシュ化するものの、
// そのファイル内部がさらに静的import する `./maplibre-gl-shared.mjs` への相対参照までは
// 書き換えない。結果としてWorker起動後にその内部importが404し、タイル化処理が永久に
// 完了しない（MunicipalityMapの市区町村境界が描画されない）。
//
// 対策として、この2ファイルをTurbopackの変換を受けない `public/` 配下にそのまま
// （ファイル名・相対位置を保ったまま）配置し、アプリ側から `setWorkerUrl()` で
// 明示的にこちらを使わせる（frontend/src/components/MunicipalityMap/index.tsx 参照）。
// maplibre-glをアップグレードするたびに `npm install` 経由で自動的に再同期される
// （package.json の "postinstall" 参照）。

import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const frontendRoot = dirname(dirname(fileURLToPath(import.meta.url)));
const srcDir = join(frontendRoot, 'node_modules', 'maplibre-gl', 'dist');
const destDir = join(frontendRoot, 'public', 'maplibre');

const files = ['maplibre-gl-worker.mjs', 'maplibre-gl-shared.mjs'];

mkdirSync(destDir, { recursive: true });

for (const file of files) {
    copyFileSync(join(srcDir, file), join(destDir, file));
    console.log(`[sync-maplibre-worker] ${file} -> public/maplibre/${file}`);
}
