import { useRouter } from 'next/router';
import { useEffect, useMemo, useRef } from 'react';
import { useAtomValue } from 'jotai';
import useSWR from 'swr';
import { budgetAtom } from '@/atoms/budget';
import MunicipalityMap, { MuniAmountEntry, MuniTier } from '@/components/MunicipalityMap';

const legendItems = [
    { color: '#2A78D6', label: '予算以下' },
    { color: '#EDA100', label: '予算から1,000万円以内' },
    { color: '#D03B3B', label: '予算から1,000万円超過' },
    { color: '#C3C2B7', label: '直近四半期の取引なし' },
    { color: '#898781', label: '全期間の取引なし' },
];

// bird_design.md §7.2 確定の初期値（セレクタUI実装（フェーズ1項目8）までの暫定固定値）
const DEFAULT_TYPE = '宅地(土地と建物)';
const DEFAULT_PRICE_CATEGORY = '取引価格';
const DEFAULT_STAT_LABEL = '平均価格';

// bird_design.md §7.2.1: 予算+1000万円までは許容（アンバー表示）
const OVER = 10_000_000;

type MuniAmountApiRow = {
    muniCode: string;
    avgTradePrice: number | null;
    medianTradePrice: number | null;
    latestCount: number;
};

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL ?? '';

const fetcher = (url: string) => fetch(url).then((res) => {
    if (!res.ok) throw new Error(`API取得に失敗しました: HTTP ${res.status}`);
    return res.json() as Promise<MuniAmountApiRow[]>;
});

// bird_design.md §7.2.1 の classify() をそのまま実装
function classify(row: MuniAmountApiRow | undefined, budget: number, statLabel: string): MuniTier {
    if (!row) return 'greyout';
    if (row.latestCount === 0) return 'lightGrey';
    const value = statLabel === '中央値' ? row.medianTradePrice : row.avgTradePrice;
    if (value == null) return 'lightGrey';
    if (value <= budget) return 'within';
    if (value <= budget + OVER) return 'orange';
    return 'red';
}

export const SearchResult: React.FC = () => {
    const router = useRouter();
    const mainRef = useRef<HTMLElement>(null);
    const budget = useAtomValue(budgetAtom);

    // budget未設定（別タブ・URL直打ち等）→ 入力画面へ戻す（bird_design.md §7.2.1）
    useEffect(() => {
        if (budget == null) {
            router.replace('/');
        }
    }, [budget, router]);

    const { data } = useSWR<MuniAmountApiRow[]>(
        `${API_BASE_URL}/muni/amounts?type=${encodeURIComponent(DEFAULT_TYPE)}&priceCategory=${encodeURIComponent(DEFAULT_PRICE_CATEGORY)}`,
        fetcher
    );

    const amountsByMuniCode = useMemo(() => {
        if (!data || budget == null) return undefined;

        const result = new Map<string, MuniAmountEntry>();

        data.forEach((row) => {
            result.set(row.muniCode, {
                tier: classify(row, budget, DEFAULT_STAT_LABEL),
                avgTradePrice: row.avgTradePrice,
                medianTradePrice: row.medianTradePrice,
                latestCount: row.latestCount,
            });
        });

        return result;
    }, [data, budget]);

    useEffect(() => {
        mainRef.current?.focus();
    }, []);

    return (
        <main ref={mainRef} tabIndex={-1}>
            <header>
                <h1>地図画面</h1>
            </header>
            <section aria-labelledby="map-heading">
                <h2 id="map-heading">市区町村の取引価格地図</h2>
                <MunicipalityMap
                    amountsByMuniCode={amountsByMuniCode}
                    dataTypeLabel={DEFAULT_TYPE}
                    priceCategoryLabel={DEFAULT_PRICE_CATEGORY}
                    statLabel={DEFAULT_STAT_LABEL}
                />
            </section>
            <section aria-labelledby="legend-heading">
                <h2 id="legend-heading">凡例</h2>
                <ul>
                    {legendItems.map(({ color, label }) => (
                        <li key={label}>
                            <span
                                aria-hidden="true"
                                style={{
                                    backgroundColor: color,
                                    border: '1px solid #333',
                                    display: 'inline-block',
                                    height: '1rem',
                                    marginRight: '0.5rem',
                                    verticalAlign: 'middle',
                                    width: '1rem',
                                }}
                            />
                            {label}
                        </li>
                    ))}
                </ul>
            </section>
            <button type="button" onClick={() => router.push('/enter-check')}>
                予算確認画面に戻る
            </button>
        </main>
    );
};

export default SearchResult;
