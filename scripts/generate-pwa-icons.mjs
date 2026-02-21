/**
 * Genera iconos PNG válidos para PWA usando solo Node.js built-in (zlib).
 * Fondo: zinc-900 (#18181b), letra "S" blanca centrada (approx via canvas de píxeles).
 *
 * Ejecutar: node scripts/generate-pwa-icons.mjs
 */

import { deflateSync } from 'node:zlib'
import { writeFileSync, mkdirSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const publicDir = join(__dirname, '..', 'public')

// ── PNG builder ──────────────────────────────────────────────────────────────

function crc32(buf) {
  let crc = 0xffffffff
  const table = new Uint32Array(256)
  for (let i = 0; i < 256; i++) {
    let c = i
    for (let j = 0; j < 8; j++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
    table[i] = c
  }
  for (const byte of buf) crc = table[(crc ^ byte) & 0xff] ^ (crc >>> 8)
  return (crc ^ 0xffffffff) >>> 0
}

function chunk(type, data) {
  const typeBytes = Buffer.from(type, 'ascii')
  const len = Buffer.alloc(4)
  len.writeUInt32BE(data.length)
  const crcInput = Buffer.concat([typeBytes, data])
  const crcBuf = Buffer.alloc(4)
  crcBuf.writeUInt32BE(crc32(crcInput))
  return Buffer.concat([len, typeBytes, data, crcBuf])
}

function buildPNG(size, bg, maskable = false) {
  // RGBA fill
  const [r, g, b] = bg
  const rowLen = size * 4
  // raw = filter byte (0) + RGBA*width per row
  const raw = Buffer.alloc(size * (1 + rowLen), 0)
  for (let y = 0; y < size; y++) {
    raw[y * (1 + rowLen)] = 0 // filter type None
    for (let x = 0; x < size; x++) {
      const offset = y * (1 + rowLen) + 1 + x * 4
      raw[offset] = r
      raw[offset + 1] = g
      raw[offset + 2] = b
      raw[offset + 3] = 255 // fully opaque

      // Draw a simple "S" shape in white — pixel art 5×7 scaled to icon
      const cx = size / 2
      const cy = size / 2
      const letterW = size * 0.28
      const letterH = size * 0.42
      const lx = x - cx
      const ly = y - cy

      // S shape using bezier-like pixel art:
      //   top bar, middle bar, bottom bar + curves
      const t = letterH / 2
      const hw = letterW / 2
      const thick = size * 0.06

      const inTopBar = ly >= -t && ly <= -t + thick && lx >= -hw && lx <= hw
      const inMidBar = Math.abs(ly) <= thick / 2 && lx >= -hw && lx <= hw
      const inBotBar = ly >= t - thick && ly <= t && lx >= -hw && lx <= hw
      const inTopRight = lx >= hw - thick && lx <= hw && ly >= -t && ly <= -t / 2
      const inBotLeft = lx >= -hw && lx <= -hw + thick && ly >= t / 2 && ly <= t
      const inTopCurveLeft = lx >= -hw && lx <= -hw + thick && ly >= -t + thick && ly <= 0
      const inBotCurveRight = lx >= hw - thick && lx <= hw && ly >= 0 && ly <= t - thick

      if (inTopBar || inMidBar || inBotBar || inTopRight || inBotLeft || inTopCurveLeft || inBotCurveRight) {
        raw[offset] = 255
        raw[offset + 1] = 255
        raw[offset + 2] = 255
      }
    }
  }

  const compressed = deflateSync(raw, { level: 9 })

  // IHDR: width, height, bitdepth=8, colortype=6 (RGBA)
  const ihdr = Buffer.alloc(13)
  ihdr.writeUInt32BE(size, 0)
  ihdr.writeUInt32BE(size, 4)
  ihdr[8] = 8  // bit depth
  ihdr[9] = 6  // color type RGBA
  ihdr[10] = 0 // compression
  ihdr[11] = 0 // filter
  ihdr[12] = 0 // interlace

  const signature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])

  return Buffer.concat([
    signature,
    chunk('IHDR', ihdr),
    chunk('IDAT', compressed),
    chunk('IEND', Buffer.alloc(0)),
  ])
}

// ── Generar iconos ───────────────────────────────────────────────────────────

const bg = [24, 24, 27] // zinc-900 #18181b

mkdirSync(publicDir, { recursive: true })

const icons = [
  { file: 'pwa-192x192.png', size: 192 },
  { file: 'pwa-512x512.png', size: 512 },
  { file: 'pwa-512x512-maskable.png', size: 512, maskable: true },
  { file: 'apple-touch-icon.png', size: 180 },
]

for (const { file, size, maskable } of icons) {
  const png = buildPNG(size, bg, maskable ?? false)
  const dest = join(publicDir, file)
  writeFileSync(dest, png)
  console.log(`✓ ${file} (${size}×${size}) → ${dest}`)
}

console.log('\n✅ Iconos PWA generados en public/')
console.log('⚠️  Reemplaza los iconos con tu logo real antes de publicar.')
