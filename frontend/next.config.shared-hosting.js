/** @type {import('next').NextConfig} */
const nextConfig = {
  // 開発環境設定
  reactStrictMode: true,
  
  // Turbopack設定（開発時のみ）
  turbopack: {
    // Turbopack configuration for Docker hot reload
  },

  // 本番環境：静的エクスポート設定
  // output: 'export' を有効にすると、next build 時に静的HTMLが生成されます
  ...(process.env.NODE_ENV === 'production' && {
    output: 'export',
    
    // 画像最適化を無効化（静的エクスポート時は必須）
    images: {
      unoptimized: true,
    },
    
    // トレイリングスラッシュを追加（共用サーバーでの互換性向上）
    trailingSlash: true,
    
    // ベースパス（サブディレクトリで公開する場合）
    // basePath: '/bird',
  }),

  // API URL設定
  env: {
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || 'https://your-domain.com/api',
  },
}

module.exports = nextConfig
