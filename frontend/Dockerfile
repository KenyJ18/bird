# ==============================================
# Bird Frontend - Next.js 15 Multi-Stage Build
# ==============================================

# ==============================================
# Stage 1: Base (Development)
# ==============================================
FROM node:20-alpine AS base

# Install pnpm
RUN corepack enable && corepack prepare pnpm@8.15.4 --activate

WORKDIR /app

# Copy package files
COPY package.json pnpm-lock.yaml* ./

# ==============================================
# Stage 2: Dependencies
# ==============================================
FROM base AS deps

# Install dependencies
RUN pnpm install --no-frozen-lockfile

# ==============================================
# Stage 3: Development
# ==============================================
FROM base AS development

# Copy dependencies from deps stage
COPY --from=deps /app/node_modules ./node_modules

# Copy source code
COPY . .

# Expose port
EXPOSE 3000

# Development server
CMD ["pnpm", "dev"]

# ==============================================
# Stage 4: Builder (for production build)
# ==============================================
FROM base AS builder

# Copy dependencies
COPY --from=deps /app/node_modules ./node_modules

# Copy source code
COPY . .

# Build application
ENV NEXT_TELEMETRY_DISABLED=1
RUN pnpm build

# ==============================================
# Stage 5: Production
# ==============================================
FROM base AS production

# Install production dependencies only
RUN pnpm install --prod --no-frozen-lockfile

# Copy built application
COPY --from=builder /app/.next ./.next
COPY --from=builder /app/public ./public
COPY --from=builder /app/next.config.js ./

# Set production environment
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1

EXPOSE 3000

CMD ["pnpm", "start"]
