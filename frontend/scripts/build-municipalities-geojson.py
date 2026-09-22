"""市区町村境界データ（1都3県・島嶼部除外）を国土数値情報N03から生成する（bird_design.md §9.2項目1）

事前準備:
  国土数値情報ダウンロードサイト（https://nlftp.mlit.go.jp/ksj/gml/datalist/KsjTmplt-N03-v2_4.html）
  から、対象4都県（東京13・神奈川14・埼玉11・千葉12）のシェープファイル形式データを
  ダウンロードし、下記 PREF_DIRS の各パスに展開しておくこと（.geojson が同梱されているため
  シェープファイルからの変換は不要）。

実行:
  python3 frontend/scripts/build-municipalities-geojson.py <出力先.geojson>

出力後、TopoJSON化するには続けて以下を実行する（frontend/ で）:
  npx geo2topo -q 1e6 municipalities=<出力先.geojson> > public/data/municipalities.json
"""

import json
import glob
import sys

ISLAND_CODES = {
    '13361', '13362', '13363', '13364', '13381',
    '13382', '13401', '13402', '13421',
}

PREF_DIRS = {
    '11': '~/Downloads/N03-20200101_11_GML',
    '12': '~/Downloads/N03-20200101_12_GML',
    '13': '~/Downloads/N03-20200101_13_GML',
    '14': '~/Downloads/N03-20200101_14_GML',
}


def build(out_path: str) -> None:
    import os

    merged_features = []
    seen_codes = set()

    for pref_code, d in PREF_DIRS.items():
        src_candidates = glob.glob(f'{os.path.expanduser(d)}/*.geojson')
        if not src_candidates:
            raise FileNotFoundError(f'{d} に .geojson が見つかりません（事前準備を参照）')
        src = src_candidates[0]

        with open(src, encoding='utf-8') as f:
            data = json.load(f)

        for feat in data['features']:
            props = feat['properties']
            muni_code = props.get('N03_007')

            if not muni_code or len(muni_code) != 5:
                continue
            if muni_code in ISLAND_CODES:
                continue

            prefecture = props.get('N03_001', '')
            # N03_003: 政令指定都市名（区を持つ市のみ）または郡名。通常の市では空文字
            # N03_004: 市区町村名本体（政令指定都市の場合は区名、それ以外は市区町村名そのもの）
            # 郡名（例: 西多摩郡）は慣例的に表示に含めないため、末尾が「市」の場合のみ結合する
            parent_area = props.get('N03_003') or None
            name = props.get('N03_004', '')
            designated_city = parent_area if parent_area and parent_area.endswith('市') else None
            full_name = f'{designated_city}{name}' if designated_city else name

            merged_features.append({
                'type': 'Feature',
                'properties': {
                    'muniCode': muni_code,
                    'prefecture': prefecture,
                    'designatedCity': designated_city,
                    'name': name,
                    'fullName': full_name,
                },
                'geometry': feat['geometry'],
            })
            seen_codes.add(muni_code)

    merged = {'type': 'FeatureCollection', 'features': merged_features}

    with open(out_path, 'w', encoding='utf-8') as f:
        json.dump(merged, f, ensure_ascii=False)

    print(f'features: {len(merged_features)}')
    print(f'unique municipality codes: {len(seen_codes)}')
    print(f'written to: {out_path}')


if __name__ == '__main__':
    if len(sys.argv) != 2:
        print(f'Usage: python3 {sys.argv[0]} <出力先.geojson>', file=sys.stderr)
        sys.exit(1)
    build(sys.argv[1])
