#!/usr/bin/env bash
# ==============================================================================
# Examify - Offline LAN SSL Certificate Generator
# Generates a self-signed Root CA and Server Certificate with Subject Alternative
# Names (SAN) for air-gapped institutional computer labs per production.md.
#
# Usage:
#   ./bin/generate-ssl.sh [DOMAIN_OR_IP] [OUTPUT_DIR]
#   ./bin/generate-ssl.sh 192.168.1.100 ./certs
# ==============================================================================
set -euo pipefail

TARGET_HOST="${1:-localhost}"
OUT_DIR="${2:-certs}"

mkdir -p "$OUT_DIR"

echo "=========================================================="
echo " Examify Offline SSL Certificate Generator"
echo " Target Host : $TARGET_HOST"
echo " Output Dir  : $OUT_DIR"
echo "=========================================================="

# Build SAN list
SAN_LIST="DNS:localhost,DNS:*.localhost,DNS:examify.local,IP:127.0.0.1,IP:::1"
if [[ "$TARGET_HOST" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    SAN_LIST="${SAN_LIST},IP:${TARGET_HOST}"
elif [ "$TARGET_HOST" != "localhost" ]; then
    SAN_LIST="${SAN_LIST},DNS:${TARGET_HOST},DNS:*.${TARGET_HOST}"
fi

# 1. Generate Root CA
echo "1. Generating Internal Root CA..."
openssl req -x509 -new -nodes -newkey rsa:4096 -sha256 -days 3650 \
    -keyout "$OUT_DIR/local-ca.key" \
    -out "$OUT_DIR/local-ca.crt" \
    -subj "/C=IN/ST=State/L=City/O=College Exam Board/CN=Examify Local Root CA"

# 2. Generate Server Key and CSR
echo "2. Generating Server Key & Signing Request..."
openssl req -new -nodes -newkey rsa:2048 \
    -keyout "$OUT_DIR/server.key" \
    -out "$OUT_DIR/server.csr" \
    -subj "/C=IN/ST=State/L=City/O=Examify/CN=${TARGET_HOST}"

# 3. Create extension config and Sign Server Certificate with Root CA
echo "3. Signing Server Certificate with SAN..."
EXT_FILE=$(mktemp)
printf "subjectAltName=%s\nextendedKeyUsage=serverAuth\nkeyUsage=digitalSignature,keyEncipherment\n" "$SAN_LIST" > "$EXT_FILE"

openssl x509 -req -in "$OUT_DIR/server.csr" \
    -CA "$OUT_DIR/local-ca.crt" \
    -CAkey "$OUT_DIR/local-ca.key" \
    -CAcreateserial \
    -out "$OUT_DIR/server.crt" \
    -days 1095 -sha256 \
    -extfile "$EXT_FILE"

rm -f "$OUT_DIR/server.csr" "$OUT_DIR/local-ca.srl" "$EXT_FILE"

chmod 600 "$OUT_DIR/server.key" "$OUT_DIR/local-ca.key"
chmod 644 "$OUT_DIR/server.crt" "$OUT_DIR/local-ca.crt"

echo ""
echo "[OK] Certificates generated successfully in $OUT_DIR:"
echo "     • Server Certificate : $OUT_DIR/server.crt"
echo "     • Server Private Key : $OUT_DIR/server.key"
echo "     • Local Root CA Cert : $OUT_DIR/local-ca.crt (Install on lab workstations)"
echo "     • Local Root CA Key  : $OUT_DIR/local-ca.key"
