import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Map, { Layer, MapRef, Popup, Source } from 'react-map-gl/maplibre';
import type { MapLayerMouseEvent } from 'react-map-gl/maplibre';
import * as topojson from 'topojson-client';
import type { Feature, FeatureCollection, Geometry } from 'geojson';
import 'maplibre-gl/dist/maplibre-gl.css';

/**
 * 市区町村ごとのハイライト判定結果（bird_design.md §7.2.1 classify() の出力）。
 * 予算・選択条件との比較は呼び出し側（検索結果画面）の責務とし、
 * このコンポーネントは渡された tier をそのまま塗り分けに使う。
 */
export type MuniTier = 'within' | 'orange' | 'red' | 'lightGrey' | 'greyout';

export interface MuniAmountEntry {
    tier: MuniTier;
    avgTradePrice: number | null;
    medianTradePrice: number | null;
    latestCount: number;
}

export interface MunicipalityMapProps {
    /** 市区町村コード（5桁）→ 判定結果。省略時は全市区町村を greyout（データなし）色で表示する */
    amountsByMuniCode?: Map<string, MuniAmountEntry>;
    /** ポップアップの見出しに使う表示ラベル（例: 「宅地(土地と建物)」） */
    dataTypeLabel?: string;
    /** ポップアップの見出しに使う表示ラベル（例: 「取引価格」） */
    priceCategoryLabel?: string;
    /** ポップアップの見出しに使う表示ラベル（例: 「平均価格」） */
    statLabel?: string;
    /** 地図の高さ（CSS値）。省略時は 24rem */
    height?: string;
}

type MunicipalityProperties = {
    muniCode: string;
    prefecture: string;
    designatedCity: string | null;
    name: string;
    fullName: string;
};

const FILL_LAYER_ID = 'municipalities-fill';
const OUTLINE_LAYER_ID = 'municipalities-outline';
const SOURCE_ID = 'municipalities';

// bird_design.md §2.3: fill-color の match 式（配色は §7.3 確定・色覚多様性検証済み）
// MapLibreスタイル式の型は@maplibre/maplibre-gl-style-specが持つが、react-map-gl配下の
// 非公開依存でありアプリコードから直接importするのは避けたいため、ここは any 配列として扱う。
const FILL_COLOR_EXPRESSION: unknown[] = [
    'match',
    ['feature-state', 'tier'],
    'within', '#2A78D6',
    'orange', '#EDA100',
    'red', '#D03B3B',
    'lightGrey', '#C3C2B7',
    /* default（feature-state未設定＝データなし） */ '#898781',
];

// bird_design.md §2.2: 国土数値情報N03を加工した1都3県・島嶼部除外の境界データ
const MUNICIPALITIES_DATA_URL = '/data/municipalities.json';

// bird_design.md §2.3: 背景地図は地理院タイル（標準地図）を採用
const GSI_STD_TILE_URL = 'https://cyberjapandata.gsi.go.jp/xyz/std/{z}/{x}/{y}.png';
const GSI_ATTRIBUTION =
    '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noopener noreferrer">地図：国土地理院</a>';

// フォールバック用の初期表示位置（1都3県の概略中心・§2.2 fitBoundsが効くまでの間だけ使う）
const INITIAL_VIEW_STATE = { longitude: 139.8, latitude: 35.55, zoom: 8 };

function formatYen(value: number | null): string {
    if (value == null) return '不明';
    return `${value.toLocaleString('ja-JP')}円`;
}

export const MunicipalityMap: React.FC<MunicipalityMapProps> = ({
    amountsByMuniCode,
    dataTypeLabel,
    priceCategoryLabel,
    statLabel,
    height = '24rem',
}) => {
    const mapRef = useRef<MapRef | null>(null);
    const [geojson, setGeojson] = useState<FeatureCollection<Geometry, MunicipalityProperties> | null>(null);
    const [mapLoaded, setMapLoaded] = useState(false);
    const [popupInfo, setPopupInfo] = useState<{
        longitude: number;
        latitude: number;
        properties: MunicipalityProperties;
    } | null>(null);

    // 境界データ（TopoJSON）を取得しGeoJSONへ変換する
    useEffect(() => {
        let cancelled = false;

        fetch(MUNICIPALITIES_DATA_URL)
            .then((res) => {
                if (!res.ok) {
                    throw new Error(`境界データの取得に失敗しました: HTTP ${res.status}`);
                }
                return res.json();
            })
            .then((topology) => {
                if (cancelled) return;
                const objectName = Object.keys(topology.objects)[0];
                const collection = topojson.feature(
                    topology,
                    topology.objects[objectName]
                ) as unknown as FeatureCollection<Geometry, MunicipalityProperties>;
                setGeojson(collection);
            })
            .catch((error) => {
                // eslint-disable-next-line no-console
                console.error('[MunicipalityMap] 境界データの読み込みに失敗しました', error);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    // 境界データ・地図の両方が準備できたら1都3県の範囲にフィットする
    useEffect(() => {
        if (!mapLoaded || !geojson || !mapRef.current) return;

        let minLng = Infinity;
        let minLat = Infinity;
        let maxLng = -Infinity;
        let maxLat = -Infinity;

        type NestedPosition = [number, number] | NestedPosition[];

        const visit = (coords: NestedPosition): void => {
            if (typeof coords[0] === 'number') {
                const [lng, lat] = coords as [number, number];
                if (lng < minLng) minLng = lng;
                if (lat < minLat) minLat = lat;
                if (lng > maxLng) maxLng = lng;
                if (lat > maxLat) maxLat = lat;
                return;
            }
            (coords as NestedPosition[]).forEach(visit);
        };

        geojson.features.forEach((feature) => {
            // 境界データはPolygonのみで構成される（build-municipalities-geojson.py参照）
            if (feature.geometry.type === 'Polygon' || feature.geometry.type === 'MultiPolygon') {
                visit(feature.geometry.coordinates as unknown as NestedPosition);
            }
        });

        if (Number.isFinite(minLng) && Number.isFinite(minLat) && Number.isFinite(maxLng) && Number.isFinite(maxLat)) {
            mapRef.current.fitBounds(
                [
                    [minLng, minLat],
                    [maxLng, maxLat],
                ],
                { padding: 24, duration: 0 }
            );
        }
    }, [mapLoaded, geojson]);

    // 判定結果（tier）を feature-state に反映する。amountsByMuniCode省略時は全て greyout 相当（fill-colorのデフォルト）
    useEffect(() => {
        if (!mapLoaded || !geojson || !mapRef.current) return;

        const seen = new Set<string>();
        for (const feature of geojson.features) {
            const muniCode = feature.properties.muniCode;
            if (seen.has(muniCode)) continue;
            seen.add(muniCode);

            const entry = amountsByMuniCode?.get(muniCode);
            mapRef.current.setFeatureState(
                { source: SOURCE_ID, id: muniCode },
                { tier: entry?.tier ?? null }
            );
        }
    }, [mapLoaded, geojson, amountsByMuniCode]);

    const handleLoad = useCallback(() => setMapLoaded(true), []);

    const handleClick = useCallback((event: MapLayerMouseEvent) => {
        const feature = event.features?.[0] as Feature<Geometry, MunicipalityProperties> | undefined;
        if (!feature) {
            setPopupInfo(null);
            return;
        }
        setPopupInfo({
            longitude: event.lngLat.lng,
            latitude: event.lngLat.lat,
            properties: feature.properties,
        });
    }, []);

    const popupBody = useMemo(() => {
        if (!popupInfo) return null;
        const entry = amountsByMuniCode?.get(popupInfo.properties.muniCode);

        if (!entry) {
            return '全期間で取引データがありません';
        }
        if (entry.latestCount === 0) {
            return '直近四半期の取引はありません';
        }
        const value = statLabel === '中央値' ? entry.medianTradePrice : entry.avgTradePrice;
        const labelParts = [dataTypeLabel, priceCategoryLabel, statLabel].filter(Boolean);
        const prefix = labelParts.length > 0 ? `${labelParts.join(' ')} ` : '';
        return `${prefix}${formatYen(value)}（取引${entry.latestCount}件）`;
    }, [popupInfo, amountsByMuniCode, dataTypeLabel, priceCategoryLabel, statLabel]);

    return (
        <div style={{ height, width: '100%' }}>
            <Map
                ref={mapRef}
                initialViewState={INITIAL_VIEW_STATE}
                mapStyle={{ version: 8, sources: {}, layers: [] }}
                interactiveLayerIds={[FILL_LAYER_ID]}
                onLoad={handleLoad}
                onClick={handleClick}
                attributionControl={{ compact: true }}
            >
                <Source
                    id="gsi-std"
                    type="raster"
                    tiles={[GSI_STD_TILE_URL]}
                    tileSize={256}
                    attribution={GSI_ATTRIBUTION}
                >
                    <Layer id="gsi-std-layer" type="raster" />
                </Source>

                {geojson && (
                    <Source id={SOURCE_ID} type="geojson" data={geojson} promoteId="muniCode">
                        <Layer
                            id={FILL_LAYER_ID}
                            type="fill"
                            paint={{
                                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                'fill-color': FILL_COLOR_EXPRESSION as any,
                                'fill-opacity': 0.55,
                            }}
                        />
                        <Layer
                            id={OUTLINE_LAYER_ID}
                            type="line"
                            paint={{ 'line-color': '#4d4d4d', 'line-width': 0.5 }}
                        />
                    </Source>
                )}

                {popupInfo && (
                    <Popup
                        longitude={popupInfo.longitude}
                        latitude={popupInfo.latitude}
                        onClose={() => setPopupInfo(null)}
                        closeOnClick={false}
                        anchor="bottom"
                    >
                        <strong>{popupInfo.properties.fullName}</strong>
                        {popupBody && <div>{popupBody}</div>}
                    </Popup>
                )}
            </Map>
        </div>
    );
};

export default MunicipalityMap;
