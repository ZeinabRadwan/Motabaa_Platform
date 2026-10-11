/**
 * Batch helper: mark files still needing AtharAppPage under src/pages
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const pagesRoot = path.join(__dirname, '../src/pages')

function walk(dir, acc = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name)
    if (ent.isDirectory()) walk(p, acc)
    else if (ent.name.endsWith('.vue')) acc.push(p)
  }
  return acc
}

const files = walk(pagesRoot).filter(f => {
  const c = fs.readFileSync(f, 'utf8')
  return c.includes('GeneralTableSection') && !c.includes('AtharAppPage')
})

console.log('Needs migration:', files.length)
for (const f of files) console.log(path.relative(pagesRoot, f))
