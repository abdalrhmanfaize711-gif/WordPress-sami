FROM node:22-bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends python3 make g++ pkg-config libvips-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv/app
ENV PATH=/srv/app/node_modules/.bin:$PATH

COPY package.json ./
RUN npm install --include=dev

COPY . .
RUN mkdir -p /srv/app/public/uploads \
    && chown -R node:node /srv/app
USER node

EXPOSE 1337
CMD ["npm", "run", "develop"]

