FROM node:20-alpine

WORKDIR /app

# Copiamos primero los archivos de dependencias para aprovechar la caché de Docker
COPY package*.json ./
RUN npm install

# Copiamos el resto del proyecto
COPY . .

# Exponemos el host para que Vite sea accesible fuera del contenedor
CMD ["npm", "run", "dev", "--", "--host"]