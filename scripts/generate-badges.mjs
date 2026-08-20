/**
 * Generate README tech badges with badge-maker (shields.io for-the-badge style).
 * Run: npm install && npm run badges   (from this folder)
 */
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { makeBadge } from 'badge-maker'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const outDir = join(root, 'docs', 'badges')
mkdirSync(outDir, { recursive: true })

const LABEL = '#3d4450'

const badges = [
  { file: 'php.svg', label: 'PHP', message: '8.3', color: '#0891b2' },
  { file: 'cakephp.svg', label: 'CakePHP', message: '4.6', color: '#2563eb' },
  { file: 'python.svg', label: 'Python', message: '3.12', color: '#ca8a04' },
  { file: 'esp32.svg', label: 'ESP32', message: 'IoT', color: '#0d9488' },
  { file: 'flask.svg', label: 'Flask', message: '3.x', color: '#059669' },
  { file: 'mysql.svg', label: 'MySQL', message: '8.x', color: '#0284c7' },
  { file: 'gemini.svg', label: 'Gemini', message: 'AI', color: '#7c3aed' },
  { file: 'license.svg', label: 'License', message: 'MIT', color: '#64748b' },
]

const svgs = badges.map((b) => {
  const svg = makeBadge({
    label: b.label,
    message: b.message,
    color: b.color,
    labelColor: LABEL,
    style: 'for-the-badge',
  })
  writeFileSync(join(outDir, b.file), svg)
  return { ...b, svg }
})

function badgeSize(svg) {
  const w = Number((svg.match(/width="(\d+(?:\.\d+)?)"/) || [])[1] || 120)
  const h = Number((svg.match(/height="(\d+(?:\.\d+)?)"/) || [])[1] || 28)
  return { w, h }
}

const primary = svgs.slice(0, 4)
const gap = 10
const sizes = primary.map((b) => badgeSize(b.svg))
const rowWidth = sizes.reduce((sum, s) => sum + s.w, 0) + gap * (sizes.length - 1)
const badgeH = Math.max(...sizes.map((s) => s.h))
const padX = 40
const padY = 28
const titleH = 36
const width = Math.max(980, rowWidth + padX * 2)
const height = padY + titleH + 18 + badgeH + padY

let x = (width - rowWidth) / 2
const badgeY = padY + titleH + 18
const badgeGroup = primary
  .map((b, i) => {
    const inner = b.svg
      .replace(/<\?xml[^>]*>/, '')
      .replace(/<svg[^>]*>/, '')
      .replace(/<\/svg>\s*$/, '')
    const g = `<g transform="translate(${x.toFixed(1)}, ${badgeY})">${inner}</g>`
    x += sizes[i].w + gap
    return g
  })
  .join('\n  ')

const header = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" role="img" aria-label="GENTA tech stack">
  <rect width="100%" height="100%" rx="8" fill="#0b1220"/>
  <text x="${width / 2}" y="${padY + 26}" text-anchor="middle" fill="#f8fafc" font-family="Verdana, DejaVu Sans, sans-serif" font-size="22" font-weight="700">AI-Powered Classroom Companion for DepEd Grade 3</text>
  ${badgeGroup}
</svg>
`

writeFileSync(join(outDir, 'header.svg'), header)
console.log(`Wrote ${svgs.length} badges + header.svg → ${outDir}`)
