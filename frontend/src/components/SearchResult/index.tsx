import { useRouter } from 'next/router';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useAtomValue } from 'jotai';
import useSWR from 'swr';
import { MenuItem, Stack, TextField } from '@mui/material';
import { budgetAtom } from '@/atoms/budget';
import MunicipalityMap, { MuniAmountEntry, MuniTier } from '@/components/MunicipalityMap';

const legendItems = [
    { color: '#2A78D6', label: '予算以下' },
    { color: '#EDA100', label: '予算から1,000万円以内' },
    { color: '#D03B3B', label: '予算から1,000万円超過' },
    { color: '#C3C2B7', label: '直近四半期の取引なし' },
    { color: '#898781', label: '全期間の取引なし' },
];

// bird_design.md §7.2 確定のセレクタ選択肢（コード昇順、初期値は先頭）
const TYPE_OPTIONS = ['宅地(土地)', '宅地(土地と建物)', '中古マンション等'];
const PRICE_CATEGORY_OPTIONS = ['取引価格', '成約価格'];
const STAT_OPTIONS = ['平均価格', '中央値'];

const DEFAULT_TYPE = TYPE_OPTIONS[1];
const DEFAULT_PRICE_CATEGORY = PRICE_CATEGORY_OPTIONS[0];
const DEFAULT_STAT_LABEL = STAT_OPTIONS[0];

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

    // セレクタの選択中の値（draft）と、「条件反映」押下で地図に反映される値（applied）を分ける
    // （bird_design.md §8.6「条件反映を押すまで地図のハイライトは変わらない」）
    const [typeDraft, setTypeDraft] = useState(DEFAULT_TYPE);
    const [priceCategoryDraft, setPriceCategoryDraft] = useState(DEFAULT_PRICE_CATEGORY);
    const [statDraft, setStatDraft] = useState(DEFAULT_STAT_LABEL);

    const [appliedType, setAppliedType] = useState(DEFAULT_TYPE);
    const [appliedPriceCategory, setAppliedPriceCategory] = useState(DEFAULT_PRICE_CATEGORY);
    const [appliedStat, setAppliedStat] = useState(DEFAULT_STAT_LABEL);

    const handleApply = () => {
        setAppliedType(typeDraft);
        setAppliedPriceCategory(priceCategoryDraft);
        setAppliedStat(statDraft);
    };

    // budget未設定（別タブ・URL直打ち等）→ 入力画面へ戻す（bird_design.md §7.2.1）
    useEffect(() => {
        if (budget == null) {
            router.replace('/');
        }
    }, [budget, router]);

    const { data } = useSWR<MuniAmountApiRow[]>(
        `${API_BASE_URL}/muni/amounts?type=${encodeURIComponent(appliedType)}&priceCategory=${encodeURIComponent(appliedPriceCategory)}`,
        fetcher
    );

    const amountsByMuniCode = useMemo(() => {
        if (!data || budget == null) return undefined;

        const result = new Map<string, MuniAmountEntry>();

        data.forEach((row) => {
            result.set(row.muniCode, {
                tier: classify(row, budget, appliedStat),
                avgTradePrice: row.avgTradePrice,
                medianTradePrice: row.medianTradePrice,
                latestCount: row.latestCount,
            });
        });

        return result;
    }, [data, budget, appliedStat]);

    useEffect(() => {
        mainRef.current?.focus();
    }, []);

    return (
        <main ref={mainRef} tabIndex={-1}>
            <header>
                <h1>地図画面</h1>
            </header>
            <section aria-labelledby="condition-heading">
                <h2 id="condition-heading">検索条件</h2>
                <Stack spacing={2}>
                    <TextField
                        label="予算"
                        variant="outlined"
                        value={budget ?? ''}
                        slotProps={{ htmlInput: { readOnly: true } }}
                    />
                    <Stack direction="row" spacing={2} alignItems="center" flexWrap="wrap">
                        <TextField
                            select
                            label="データ種類"
                            value={typeDraft}
                            onChange={(event) => setTypeDraft(event.target.value)}
                        >
                            {TYPE_OPTIONS.map((option) => (
                                <MenuItem key={option} value={option}>{option}</MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="価格情報区分"
                            value={priceCategoryDraft}
                            onChange={(event) => setPriceCategoryDraft(event.target.value)}
                        >
                            {PRICE_CATEGORY_OPTIONS.map((option) => (
                                <MenuItem key={option} value={option}>{option}</MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            label="統計指標"
                            value={statDraft}
                            onChange={(event) => setStatDraft(event.target.value)}
                        >
                            {STAT_OPTIONS.map((option) => (
                                <MenuItem key={option} value={option}>{option}</MenuItem>
                            ))}
                        </TextField>
                        <button type="button" onClick={handleApply}>
                            条件反映
                        </button>
                    </Stack>
                </Stack>
            </section>
            <section aria-labelledby="map-heading">
                <h2 id="map-heading">市区町村の取引価格地図</h2>
                <MunicipalityMap
                    amountsByMuniCode={amountsByMuniCode}
                    dataTypeLabel={appliedType}
                    priceCategoryLabel={appliedPriceCategory}
                    statLabel={appliedStat}
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
