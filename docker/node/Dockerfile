FROM node:20-alpine

WORKDIR /app

# package.json が存在する場合のみ install を実行
COPY frontend/package*.json ./
RUN if [ -f package.json ]; then npm install; fi

EXPOSE 3000

CMD ["npm", "run", "dev"]
