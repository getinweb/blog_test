ARG TARGETARCH

# Update the release URLs and their SHA-256 checksums together.
# Checksums: https://github.com/sass/dart-sass/releases/tag/1.105.1
FROM scratch AS archive-amd64
ADD --checksum=sha256:e497419d75c5be842ae53c280c3749b61fdc9eddd72960640323d277ad7579d5 https://github.com/sass/dart-sass/releases/download/1.105.1/dart-sass-1.105.1-linux-x64-musl.tar.gz /sass.tar.gz

FROM scratch AS archive-arm64
ADD --checksum=sha256:30d586373af18b2f6c66aca8d7aed37674a463742763fa8f40416b016d9f20c9 https://github.com/sass/dart-sass/releases/download/1.105.1/dart-sass-1.105.1-linux-arm64-musl.tar.gz /sass.tar.gz

FROM archive-${TARGETARCH} AS archive

FROM alpine:3.24.2@sha256:294b683cb724975bec92580e1e685676bd4b50bda910ddb8c51d4cabeaec77e6

COPY --from=archive /sass.tar.gz /tmp/sass.tar.gz

RUN tar -xzf /tmp/sass.tar.gz -C /opt \
    && rm /tmp/sass.tar.gz \
    && /opt/dart-sass/sass --version

WORKDIR /var/www/app

ENTRYPOINT ["/opt/dart-sass/sass"]
CMD ["--help"]
