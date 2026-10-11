import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '../src/pages')

function extractShell(content) {
  const start = content.indexOf('<AtharAppPage>')
  const end = content.indexOf('<GeneralTableSection', start)
  if (start === -1 || end === -1) return null
  return content.slice(start, end)
}

function migrateFile(rel, replacements) {
  const goalsPath = path.join(root, 'goals/list/index.vue')
  const targetPath = path.join(root, rel)
  let shell = extractShell(fs.readFileSync(goalsPath, 'utf8'))
  if (!shell) {
    console.log('skip no shell:', rel)
    return
  }
  for (const [from, to] of replacements) shell = shell.split(from).join(to)

  let content = fs.readFileSync(targetPath, 'utf8')
  if (content.includes('AtharAppPage')) {
    console.log('skip done:', rel)
    return
  }

  const re = /<template>[\s\S]*?<!-- SECTION datatable -->\s*\n\s*<GeneralTableSection/
  if (!re.test(content)) {
    console.log('skip pattern:', rel)
    return
  }

  content = content.replace(re, `<template>\n  ${shell}<GeneralTableSection`)
  content = content.replace(
    /<\/GeneralTableSection>\s*<!-- SECTION -->\s*<\/VCard>\s*<\/[Vv]Col>\s*<\/[Vv]Row>/,
    '</GeneralTableSection>\n    </AtharPagePanel>',
  )
  content = content.replace(/\s*<\/section>\s*\n<\/template>/, '\n  </AtharAppPage>\n</template>')

  fs.writeFileSync(targetPath, content)
  console.log('migrated:', rel)
}

const independentRepl = [
  ['goals.education_goals', 'independent_goals.independent_goals'],
  ['edit_education-goals', 'edit_independent-goals'],
  ['goals.add_behavioral_goal', 'goals.add_goal'],
  ["printPlans('case_planning')", "printPlans('independent_case_planning')"],
  [':disabled="!hasCaseAndTerm"', ':disabled="selectedTerm == \'\' || selectedTerm == null"'],
]

const servicesRepl = [
  ['goals.education_goals', 'services'],
  ['edit_education-goals', 'edit_treatment-goals'],
  ['goals.add_behavioral_goal', 'goals.add_goal'],
  ["printPlans('case_planning')", "printPlans('services_case_planning')"],
  [':disabled="!hasCaseAndTerm"', ':disabled="selectedTerm == \'\' || selectedTerm == null"'],
]

const migrateTargets = [
  ['independent_goals/list/index.vue', independentRepl],
  ['services/list/index.vue', servicesRepl],
  ['goals/list/[case]/[term].vue', [
    ...independentRepl.map(([a, b]) => [a, b]),
    [':disabled="!hasCaseAndTerm"', ''],
    ['goals.add_behavioral_goal', 'goals.add_behavioral_goal'],
  ]],
  ['independent_goals/list/[case]/[term].vue', independentRepl],
]

for (const [rel, repl] of migrateTargets) migrateFile(rel, repl)

// Add scale type to services after opening filters template
const servicesPath = path.join(root, 'services/list/index.vue')
let svc = fs.readFileSync(servicesPath, 'utf8')
if (svc.includes('AtharAppPage') && !svc.includes('selectedScaleType')) {
  svc = svc.replace(
    '<template #filters>\n          <AppAutocomplete',
    `<template #filters>
          <AppAutocomplete
            :items="scaleTypeFilterItems()"
            v-model="selectedScaleType"
            class="general-filter__control"
            :label="$t('goals.service_type')"
            clearable
            clear-icon="tabler-x"
            density="compact"
            hide-details
          />
          <AppAutocomplete`,
  )
  svc = svc.replace(
    ':label="$t(\'teacher\')"',
    ':label="$t(\'specialists\')"',
  )
  svc = svc.replace('searchTeachers', 'searchSpecialists')
  svc = svc.replace(':preload-key="teacher"', ':preload-key="specialist"')
  svc = svc.replace('v-model="teacher"', 'v-model="specialist"')
  fs.writeFileSync(servicesPath, svc)
  console.log('patched services filters')
}
