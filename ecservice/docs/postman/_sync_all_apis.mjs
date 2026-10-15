/**
 * Regenerate Postman coverage from Laravel routes:
 *   cd ecservice && php -r "file_put_contents('docs/postman/_routes.json', shell_exec('php artisan route:list --path=api --json'));"
 *   node docs/postman/_sync_all_apis.mjs
 */
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const routes = JSON.parse(fs.readFileSync(path.join(__dirname, '_routes.json'), 'utf8'))
const colPath = path.join(__dirname, 'Athar-Platform-LIVE.postman_collection.json')
const col = JSON.parse(fs.readFileSync(colPath, 'utf8'))

const SKIP_URI = /^api\/(mail|upload_files_to_bunny|fix)$/

const PARAM_VARS = {
  user: 'user_id',
  case: 'case_id',
  goal: 'goal_id',
  term: 'term_id',
  room: 'meeting_room_id',
  task: 'questionnaire_task_id',
  activity: 'activity_id',
  entry: 'activity_entry_id',
  payment: 'payment_id',
  fee: 'fee_id',
  plan: 'plan_id',
  assessment: 'assessment_id',
  message: 'message_id',
  step: 'goal_step_id',
  attendance: 'employee_attendance_id',
  role: 'role_id',
  token: 'verify_token',
  pdfType: 'pdf_type',
  id: 'record_id',
}

function resolveParamVar(group, param) {
  if (param === 'id') {
    if (group === 'centers') return 'center_id'
    if (group === 'users') return 'user_id'
    if (group === 'employee_leaves') return 'leave_id'
    if (group === 'questionnaires') return 'questionnaire_task_id'
    return 'record_id'
  }
  return PARAM_VARS[param] || `${param}_id`
}

function uriToPostmanPath(uri) {
  const parts = uri.split('/').filter(Boolean)
  if (parts[0] === 'api') parts.shift()
  const group = parts[0] || ''
  return parts.map(seg => {
    const m = seg.match(/^\{([^}?]+)\??\}$/)
    if (m) return `{{${resolveParamVar(group, m[1])}}}`
    return seg
  })
}

function signature(method, uri) {
  const parts = uri.replace(/^api\//, '').split('/').map(s => {
    const m = s.match(/^\{([^}?]+)\??\}$/)
    return m ? `{${m[1]}}` : s
  })
  return `${method.toUpperCase()} ${parts.join('/')}`
}

function collectPostmanSignatures(node, out = new Set()) {
  if (node.request?.url) {
    const method = (node.request.method || 'GET').toUpperCase()
    const u = node.request.url
    let parts = []
    if (typeof u === 'object' && Array.isArray(u.path)) {
      parts = u.path.map(p => String(p).replace(/^\{\{[^}]+\}\}$/, '{id}'))
    } else {
      let raw = typeof u === 'string' ? u : u.raw || ''
      raw = raw.replace(/\{\{base_url\}\}/g, '').replace(/^\//, '').replace(/^api\//, '')
      raw = raw.replace(/\?.*$/, '')
      parts = raw.split('/').map(p => (p.match(/^\{\{/) ? '{id}' : p))
    }
    out.add(`${method} ${parts.join('/')}`)
  }
  if (node.item) node.item.forEach(ch => collectPostmanSignatures(ch, out))
  return out
}

const covered = collectPostmanSignatures({ item: col.item })

// Re-check covered with fuzzy: normalize {id} for all params
function normalizeSig(method, uri) {
  const parts = uri.replace(/^api\//, '').split('/').map(s =>
    s.match(/^\{/) ? '*' : s,
  )
  return `${method.toUpperCase()} ${parts.join('/')}`
}

const coveredNorm = new Set()
for (const s of covered) {
  coveredNorm.add(s.replace(/\{id\}/g, '*').replace(/\/\*/g, '/*'))
}

function isCovered(route) {
  const n = normalizeSig(route.method, route.uri)
  if ([...coveredNorm].some(c => c === n)) return true
  const sig = signature(route.method, route.uri)
  if (covered.has(sig)) return true
  // wildcard match same length
  const np = n.split(' ')
  const npath = np[1]?.split('/') || []
  for (const c of coveredNorm) {
    const [cm, ...rest] = c.split(' ')
    const cp = rest.join(' ').split('/')
    if (cm !== np[0] || cp.length !== npath.length) continue
    let ok = true
    for (let i = 0; i < cp.length; i++) {
      if (cp[i] !== '*' && cp[i] !== npath[i]) {
        ok = false
        break
      }
    }
    if (ok) return true
  }
  return false
}

const authHeaders = [
  { key: 'Accept', value: 'application/json' },
  { key: 'Accept-Language', value: '{{accept_language}}' },
]

const noAuthPrefixes = ['api/auth/', 'api/users/register', 'api/users/verify/', 'api/file/download', 'api/centers/packages']

function needsAuth(uri) {
  return !noAuthPrefixes.some(p => uri.startsWith(p) || uri === p.replace(/\/$/, ''))
}

function makeRequest(route) {
  const pathParts = uriToPostmanPath(route.uri)
  const method = route.method.toUpperCase()
  const name = `[${method}] ${pathParts.join('/')}`
  const headers = [...authHeaders]
  if (['POST', 'PUT', 'PATCH'].includes(method) && !route.uri.includes('add_file') && !route.uri.includes('upload_file')) {
    headers.push({ key: 'Content-Type', value: 'application/json' })
  }

  const req = {
    name,
    request: {
      method,
      header: headers,
      url: {
        raw: `{{base_url}}/${pathParts.join('/')}`,
        host: ['{{base_url}}'],
        path: pathParts,
      },
      description: `Auto-synced from routes/api.php (${method} /${route.uri.replace(/^api\//, '')}).\n\nAction: ${route.action || 'n/a'}\n\nMutating endpoints: prefer Athar LOCAL env.\n\nAuth: ${needsAuth(route.uri) ? 'Bearer token' : 'None'}`,
    },
  }

  if (method === 'GET' && route.uri.includes('center') && !route.uri.includes('centers/packages')) {
    const q = pathParts.includes('{{center_id}}') ? [] : [{ key: 'center_id', value: '{{center_id}}' }]
    if (q.length) {
      req.request.url.query = q
      req.request.url.raw += '?center_id={{center_id}}'
    }
  }

  if (['POST', 'PUT', 'PATCH'].includes(method)) {
    const isMultipart =
      route.uri.includes('add_file') ||
      route.uri.includes('upload_file') ||
      route.uri.includes('/import') ||
      route.uri.includes('account/profile') ||
      route.uri.includes('cases/create') ||
      route.uri.includes('cases/') && route.uri.includes('update')

    if (isMultipart) {
      req.request.body = {
        mode: 'formdata',
        formdata: [{ key: 'note', value: 'See web form / controller for required fields', type: 'text' }],
      }
      req.request.header = req.request.header.filter(h => h.key !== 'Content-Type')
    } else {
      req.request.body = {
        mode: 'raw',
        raw: '{}',
        options: { raw: { language: 'json' } },
      }
    }
  }

  if (!needsAuth(route.uri)) {
    req.request.auth = { type: 'noauth' }
  }

  return req
}

const missingRoutes = routes.filter(r => r.uri.startsWith('api/') && !SKIP_URI.test(r.uri) && !isCovered(r))

const byGroup = {}
for (const r of missingRoutes) {
  const seg = r.uri.replace(/^api\//, '').split('/')[0] || 'root'
  if (!byGroup[seg]) byGroup[seg] = []
  byGroup[seg].push(r)
}

const REF_FOLDER = 'API reference (complete — auto-sync)'
let refRoot = col.item.find(i => i.name === REF_FOLDER)
if (!refRoot) {
  refRoot = { name: REF_FOLDER, item: [], description: 'Every route from `php artisan route:list --path=api` not already covered by named workflow folders above. Regenerate via `node docs/postman/_sync_all_apis.mjs`. Last sync: 2026-10-15.' }
  col.item.push(refRoot)
} else {
  refRoot.item = []
  refRoot.description = 'Every route from `php artisan route:list --path=api` not already covered by named workflow folders above. Regenerate via `node docs/postman/_sync_all_apis.mjs`. Last sync: 2026-10-15.'
}

for (const group of Object.keys(byGroup).sort()) {
  const items = byGroup[group]
    .sort((a, b) => signature(a.method, a.uri).localeCompare(signature(b.method, b.uri)))
    .map(makeRequest)
  refRoot.item.push({
    name: group,
    item: items,
    description: `${items.length} endpoint(s) — prefix \`/api/${group}\``,
  })
}

const ALL_VARS = [
  ['user_id', '1', 'User / parent / employee id'],
  ['record_id', '1', 'Generic id path param'],
  ['meeting_room_id', '1', 'Meeting room'],
  ['questionnaire_task_id', '1', 'Questionnaire task id'],
  ['activity_id', '1', 'Center activity'],
  ['activity_entry_id', '1', 'Activity feed entry'],
  ['payment_id', '1', 'Study fee payment'],
  ['fee_id', '1', 'Case fee'],
  ['plan_id', '1', 'Operational plan'],
  ['assessment_id', '1', 'Assessment scale node'],
  ['employee_attendance_id', '1', 'Employee attendance record'],
  ['role_id', '1', 'Spatie role id'],
  ['verify_token', '', 'User verify or reset token in URL'],
  ['leave_id', '1', 'Employee leave request'],
  ['log_type', 'cases', 'Dashboard log filter'],
]

function mergeVars(target) {
  for (const [key, value, description] of ALL_VARS) {
    if (!target.some(v => v.key === key)) {
      target.push({ key, value, type: key.includes('token') || key.includes('password') ? 'secret' : 'default', enabled: true, ...(description ? { description } : {}) })
    }
  }
}

mergeVars(col.variable)

function syncEnv(file) {
  const env = JSON.parse(fs.readFileSync(file, 'utf8'))
  mergeVars(env.values)
  env._postman_exported_at = new Date().toISOString()
  fs.writeFileSync(file, JSON.stringify(env, null, 2) + '\n')
}

col.info.description =
  'Live admin API (`https://api.athrplatform.com/api`). Frontend: `https://platform.athrplatform.com`.\n\n' +
  '**Start:** **Login** → Bearer `{{token}}` (collection auth).\n\n' +
  '**Workflow folders** mirror the web app (cases, goals, fees, …).\n\n' +
  '**API reference (complete — auto-sync)** lists any remaining `routes/api.php` endpoints (run `node ecservice/docs/postman/_sync_all_apis.mjs` after route changes).\n\n' +
  '**Environments:** `Athar-Platform-LIVE.postman_environment.json` (production) and `Athar-LOCAL.postman_environment.json` (`http://127.0.0.1:8000/api`). Prefer LOCAL for mutating tests.'

fs.writeFileSync(colPath, JSON.stringify(col, null, 2) + '\n')
syncEnv(path.join(__dirname, 'Athar-Platform-LIVE.postman_environment.json'))
syncEnv(path.join(__dirname, 'Athar-LOCAL.postman_environment.json'))

console.log('Missing routes added:', missingRoutes.length)
console.log('Reference groups:', Object.keys(byGroup).length)
console.log('Total requests now:', (() => {
  let n = 0
  const walk = x => {
    if (x.request) n++
    x.item?.forEach(walk)
  }
  col.item.forEach(walk)
  return n
})())
