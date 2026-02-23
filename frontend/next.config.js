/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // Next.js 16 uses Turbopack by default
  turbopack: {
    // Turbopack configuration for Docker hot reload
  },
}

module.exports = nextConfig
