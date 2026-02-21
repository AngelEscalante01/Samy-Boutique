/**
 * Genera los iconos PWA a partir del logo fuente.
 *
 * REQUISITO: Guarda el logo en  public/logo-source.png  antes de ejecutar.
 *
 * Uso:
 *   node scripts/resize-pwa-icons.mjs
 */

import sharp from 'sharp'
import { existsSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const publicDir = join(__dirname, '..', 'public')
const source = join(publicDir, 'logo-source.png')

if (!existsSync(source)) {
  console.error('❌ No se encontró public/logo-source.png')
  console.error('   Guarda el logo en esa ruta y vuelve a ejecutar este script.')
  process.exit(1)
}

const icons = [
  // manifest icons
  { file: 'pwa-192x192.png',          size: 192, padding: 0.10 },
  { file: 'pwa-512x512.png',          size: 512, padding: 0.10 },
  // maskable: logo más pequeño con margen amplio (zona segura = 80%)
  { file: 'pwa-512x512-maskable.png', size: 512, padding: 0.20 },
  // iOS
  { file: 'apple-touch-icon.png',     size: 180, padding: 0.10 },
  // favicon
  { file: 'favicon-32.png',           size: 32,  padding: 0.05 },
]

for (const { file, size, padding } of icons) {
  const inner = Math.round(size * (1 - padding * 2))
  const offset = Math.round(size * padding)

  // Redimensiona el logo respetando aspect ratio y lo compone sobre fondo blanco
  const resized = await sharp(source)
    .resize(inner, inner, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 0 } })
    .toBuffer()

  await sharp({
    create: {
      width: size,
      height: size,
      channels: 4,
      background: { r: 255, g: 255, b: 255, alpha: 255 }, // fondo blanco
    },
  })
    .composite([{ input: resized, top: offset, left: offset }])
    .png({ compressionLevel: 9 })
    .toFile(join(publicDir, file))

  console.log(`✓ ${file}  (${size}×${size})`)
}

console.log('\n✅ Iconos generados. Ahora ejecuta:  npm run build')
