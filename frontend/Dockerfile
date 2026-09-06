# Etapa de compilación
FROM node:20-alpine as build
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .

# Aumentar el límite de memoria del V8 Engine a 4GB
# ENV NODE_OPTIONS="--max-old-space-size=4096"
RUN npm run build

# Etapa de producción
FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]