import { atomWithStorage, createJSONStorage } from 'jotai/utils';

/**
 * budget の受け渡し方式（bird_design.md §7.1確定）
 *
 * sessionStorage backedのatomWithStorageを使う。クエリパラメータ方式（URLで共有可能）より
 * budget（個人の予算額）をURL・ブラウザ履歴に残さないプライバシー面を優先した。
 * sessionStorageはタブ単位のため、直リロードでは値が残るが別タブ・URL直打ちでは消える
 * （§8注意点2のフォールバックが各画面に必要）。
 *
 * Next.jsの静的書き出し（ビルド時のプリレンダリング）ではwindow/sessionStorageが
 * 存在しないため、undefinedを返して安全にフォールバックする。
 */
const getSessionStorage = (): Storage =>
    (typeof window === 'undefined' ? undefined : window.sessionStorage) as Storage;

const storage = createJSONStorage<number | null>(getSessionStorage);

export const budgetAtom = atomWithStorage<number | null>('bird.budget', null, storage, {
    getOnInit: true,
});
