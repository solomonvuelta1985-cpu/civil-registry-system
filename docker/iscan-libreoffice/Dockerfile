FROM debian:bookworm-slim

ENV HOME=/tmp

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        libreoffice-writer \
        fonts-dejavu \
        fonts-liberation \
        fontconfig \
    && rm -rf /var/lib/apt/lists/*

ENTRYPOINT ["soffice"]
