# Stage 1: Build Vue frontend
FROM node:22-alpine AS frontend-builder

WORKDIR /app

COPY frontend/muhofaza/package*.json ./
RUN npm ci

COPY frontend/muhofaza/ .

ARG VITE_API_BASE_URL=/api
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL

RUN npm run build

# Stage 2: Nginx
FROM nginx:1.27-alpine

# Remove default config
RUN rm /etc/nginx/conf.d/default.conf

# Nginx config
COPY nginx/default.conf /etc/nginx/conf.d/default.conf

# Vue dist
COPY --from=frontend-builder /app/dist /var/www/frontend

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
