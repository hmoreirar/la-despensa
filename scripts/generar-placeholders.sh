#!/usr/bin/env bash
# Genera placeholders de producto con ImageMagick.
# Uso: ./scripts/generar-placeholders.sh
# Reemplazables luego por fotos reales o generadas, conservando el nombre.

set -Eeuo pipefail
cd "$(dirname "$0")/.."

OUT="assets/productos"
FONT="/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
BOLD="/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"

if ! command -v magick >/dev/null 2>&1; then
  echo "Falta ImageMagick (comando magick)." >&2
  exit 1
fi

mkdir -p "$OUT"

while IFS='|' read -r sku category name; do
  [ -z "$sku" ] && continue

  magick -size 1024x1024 gradient:'#F7F1E6'-'#E8DAC6' \
    -fill '#F1E7D7' -draw 'circle 512,500 512,255' \
    -stroke '#D9C9B2' -strokewidth 2 -fill none -draw 'rectangle 36,36 988,988' \
    -font "$FONT" -fill '#8B7C66' -pointsize 22 -kerning 6 \
    -gravity north -annotate +0+92 "$category" \
    \( -background none -font "$BOLD" -fill '#3D3830' -pointsize 60 \
       -interline-spacing 10 -size 720x300 -gravity center caption:"$name" \) \
    -gravity center -geometry +0+15 -composite \
    -font "$FONT" -fill '#8B7C66' -pointsize 22 -kerning 6 \
    -gravity south -annotate +0+96 "LA DESPENSA" \
    -quality 88 "$OUT/$sku.jpg"

  echo "placeholder: $sku"
done <<'DATA'
LD-ACE-001|ACEITES Y CONDIMENTOS|Aceite de oliva extra virgen
LD-SAL-001|ACEITES Y CONDIMENTOS|Sal de mar de Cahuil
LD-MER-001|ACEITES Y CONDIMENTOS|Merkén artesanal
LD-MERM-001|MERMELADAS Y CONSERVAS|Mermelada artesanal
LD-CON-001|MERMELADAS Y CONSERVAS|Conserva de tomate cherry
LD-CAF-001|CAFÉ Y TÉ|Café de especialidad en grano
LD-CAF-002|CAFÉ Y TÉ|Café molido
LD-TE-001|CAFÉ Y TÉ|Té negro Ceylán
LD-INF-001|CAFÉ Y TÉ|Infusión de hierbas
LD-ART-001|PRODUCTOS ARTESANALES|Bowl de greda
LD-ART-002|PRODUCTOS ARTESANALES|Tabla de madera nativa
LD-ART-003|PRODUCTOS ARTESANALES|Set de cucharas de madera
LD-PAN-001|PANADERÍA Y REPOSTERÍA|Pan de masa madre
LD-PAN-002|PANADERÍA Y REPOSTERÍA|Galletas de avena
LD-PAN-003|PANADERÍA Y REPOSTERÍA|Queque de manjar
LD-PAN-004|PANADERÍA Y REPOSTERÍA|Alfajores artesanales
DATA
