import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const pagesRoot = path.join(__dirname, '../src/pages')

const configs = [
  { rel: 'attendances/list/index.vue', title: "$t('Attendance')", primary: null },
  { rel: 'cases/list/index.vue', title: "$t('Cases')", primary: 'cases', preview: true },
  { rel: 'goals/list/index.vue', title: "$t('goals.education_goals')", primary: 'goals_menu' },
  { rel: 'goals/list/[case]/[term].vue', title: "$t('goals.education_goals')", primary: 'goals_menu' },
  { rel: 'goals/sessions/index.vue', title: "$t('goals.sessions')", primary: null },
  { rel: 'goals/assessments/index.vue', title: "$t('Assessments')", primary: null },
  { rel: 'independent_goals/list/index.vue', title: "$t('independent_goals.independent_goals')", primary: 'ig_menu' },
  { rel: 'independent_goals/list/[case]/[term].vue', title: "$t('independent_goals.independent_goals')", primary: 'ig_menu' },
  { rel: 'independent_goals/sessions/index.vue', title: "$t('goals.sessions')", primary: null },
  { rel: 'independent_goals/sessions/[case]/[term]/[goal].vue', title: "$t('goals.sessions')", primary: null },
  { rel: 'independent_goals/assessments/index.vue', title: "$t('Assessments')", primary: null },
  { rel: 'payments/list/index.vue', title: "$t('payments.Payments')", primary: 'payments_add' },
  { rel: 'payments/list/[case]/[term]/[fee].vue', title: "$t('payments.Payments')", primary: null },
  { rel: 'services/list/index.vue', title: "$t('services')", primary: 'services_menu' },
  { rel: 'services/list/[case]/[term]/[service_type].vue', title: "$t('services')", primary: null },
  { rel: 'services/assessments/index.vue', title: "$t('Assessments')", primary: null },
]

function addControlClass(snippet) {
  return snippet
    .replace(/<App(Select|Autocomplete|TextField|DateTimePicker)\b/g, '<App$1\n            class="general-filter__control"')
    .replace(/class="general-filter__control"\s+class="general-filter__control"/g, 'class="general-filter__control"')
    .replace(/class="([^"]*)\s+general-filter__control"/g, (m, c) => `class="${c} general-filter__control"`)
}

function stripFilterCols(html) {
  let out = html
  out = out.replace(/<VCol[^>]*>\s*/g, '')
  out = out.replace(/<\/VCol>\s*/g, '')
  out = out.replace(/<VRow[^>]*>\s*(?=<VCol|<App|<VLabel|<div)/g, '')
  out = out.replace(/<\/VRow>\s*(?=<VCol|<App|<VLabel|<div)/g, '')
  out = out.replace(/<VRow>\s*<\/VRow>/g, '')
  return addControlClass(out)
}

function extractToolbar(toolbarHtml) {
  const pageSizeMatch = toolbarHtml.match(/<AppSelect[\s\S]*?itemsPerPage[\s\S]*?<\/AppSelect>/)
  const searchMatch = toolbarHtml.match(/<App(TextField|Autocomplete)[\s\S]*?searchQuery[\s\S]*?<\/App(?:TextField|Autocomplete)>/)
  const buttons = toolbarHtml.match(/<VBtn[\s\S]*?<\/VBtn>/g) || []
  const menus = toolbarHtml.match(/<VMenu[\s\S]*?<\/VMenu>/g) || []
  return { pageSizeMatch, searchMatch, buttons, menus }
}

function migrateFile(rel, cfg) {
  const filePath = path.join(pagesRoot, rel)
  let content = fs.readFileSync(filePath, 'utf8')
  if (content.includes('AtharAppPage')) {
    console.log('skip (done):', rel)
    return
  }

  const tableIdx = content.indexOf('<GeneralTableSection')
  if (tableIdx === -1) {
    console.log('skip (no table):', rel)
    return
  }

  const templateIdx = content.indexOf('<template>')
  let head = content.slice(0, templateIdx + '<template>'.length)

  const afterTemplate = content.slice(templateIdx + '<template>'.length, tableIdx)

  const filterBlockRe = /<!-- 👉 Filters -->[\s\S]*?<VCardText>\s*<VRow>([\s\S]*?)<\/VRow>\s*<\/VCardText>\s*<VDivider\s*\/?>\s*<VCardText class="d-flex flex-wrap py-4 gap-4"[^>]*>([\s\S]*?)<\/VCardText>\s*<VDivider\s*\/?>\s*<!-- SECTION datatable -->/i
  const altRe = /<!-- 👉 Filters -->[\s\S]*?<VCardText>\s*<VRow>([\s\S]*?)<\/VRow>\s*<\/VCardText>\s*<VDivider\s*\/?>\s*<VCardText class="d-flex flex-wrap py-4 gap-4"[^>]*>([\s\S]*?)<\/VCardText>\s*<VDivider\s*\/?>\s*\n\s*<!-- SECTION datatable -->/i

  let m = afterTemplate.match(filterBlockRe) || afterTemplate.match(altRe)
  if (!m) {
    console.log('skip (pattern):', rel)
    return
  }

  const filtersInner = stripFilterCols(m[1])
  const toolbar = m[2]
  const { pageSizeMatch, searchMatch, buttons, menus } = extractToolbar(toolbar)

  let primarySlot = ''
  if (cfg.primary === 'goals_menu') {
    primarySlot = `
      <template #primary-action>
        <VMenu v-if="can('edit_education-goals','edit_education-goals')">
          <template #activator="{ props }">
            <VBtn
              class="athar-page-header__btn-primary"
              prepend-icon="tabler-plus"
              :disabled="!hasCaseAndTerm"
              v-bind="props"
            >
              {{ $t('goals.add_behavioral_goal') }}
            </VBtn>
          </template>
          <VList>
            <VListItem @click="AddGaol(false)">{{ $t('with_feild') }}</VListItem>
            <VListItem @click="AddGaol(true)">{{ $t('without_feild') }}</VListItem>
          </VList>
        </VMenu>
      </template>`
  } else if (cfg.primary === 'ig_menu') {
    primarySlot = `
      <template #primary-action>
        <VMenu v-if="can('edit_independent-goals','edit_independent-goals')">
          <template #activator="{ props }">
            <VBtn
              class="athar-page-header__btn-primary"
              prepend-icon="tabler-plus"
              :disabled="!hasCaseAndTerm"
              v-bind="props"
            >
              {{ $t('goals.add_behavioral_goal') }}
            </VBtn>
          </template>
          <VList>
            <VListItem @click="AddGaol(false)">{{ $t('with_feild') }}</VListItem>
            <VListItem @click="AddGaol(true)">{{ $t('without_feild') }}</VListItem>
          </VList>
        </VMenu>
      </template>`
  }

  const pageSize = pageSizeMatch
    ? pageSizeMatch[0]
        .replace('style="width: 6.25rem;"', '')
        .replace('<AppSelect', '<AppSelect\n            class="general-filter__page-size"')
        .replace(/(density="compact"\s*)?/i, 'density="compact"\n            hide-details\n            ')
    : `<AppSelect
            :model-value="options.itemsPerPage"
            class="general-filter__page-size"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: 'All' },
            ]"
            density="compact"
            hide-details
            @update:model-value="options.itemsPerPage = parseInt($event, 10)"
          />`

  const searchBlock = searchMatch
    ? searchMatch[0].replace(/style="[^"]*"/g, '').replace('<App', '<App\n            class="general-filter__control" hide-details density="compact"')
    : ''

  const actionBtns = [...buttons, ...menus]
    .filter(b => !b.includes('add_behavioral_goal') && !b.includes('AddGaol'))
    .map(b => b.replace(/color="success"/g, 'variant="tonal" color="primary"').replace(/prepend-icon="tabler-plus"[\s\S]*?<\/VBtn>/, '</VBtn>'))
    .join('\n          ')

  const previewWrap = cfg.preview ? ' v-if="!preview"' : ''
  const shellOpen = `
  <AtharAppPage${previewWrap}>
    <AtharPageHeader
      :title="${cfg.title}"
      :subtitle="$t('athar.page.list_subtitle')"
    >${primarySlot}
    </AtharPageHeader>

    <AtharPagePanel>
      <GeneralFilter embedded>
        <template #prepend>
          ${pageSize}
        </template>
        ${searchBlock ? `<template #search>\n          ${searchBlock}\n        </template>` : ''}
        <template #filters>
          ${filtersInner}
        </template>
        ${actionBtns ? `<template #actions>\n          ${actionBtns}\n        </template>` : ''}
      </GeneralFilter>

      `

  let tail = content.slice(tableIdx)
  tail = tail.replace(/<!-- SECTION -->\s*<\/GeneralTableSection>\s*<!-- SECTION -->\s*<\/VCard>\s*<\/VCol>\s*<\/VRow>/, '</GeneralTableSection>\n    </AtharPagePanel>')
  tail = tail.replace(/<!-- SECTION -->\s*<\/GeneralTableSection>\s*<!-- SECTION -->[\s\S]*?<\/VCard>\s*<\/VCol>\s*<\/VRow>/, '</GeneralTableSection>\n    </AtharPagePanel>')
  tail = tail.replace(/<\/GeneralTableSection>\s*<!-- SECTION -->\s*<\/VCard>\s*<\/VCol>\s*<\/VRow>/, '</GeneralTableSection>\n    </AtharPagePanel>')

  let rest = content.slice(content.indexOf('</template>', tableIdx))
  rest = rest.replace(/\s*<\/section>\s*<\/template>/, '\n  </AtharAppPage>\n</template>')
  rest = rest.replace(/\s*<\/section>\s*\n\s*<\/template>/, '\n  </AtharAppPage>\n</template>')

  const newContent = head + shellOpen + tail
  const finalContent = newContent.includes('</AtharAppPage>') ? newContent : newContent.replace('</template>', '  </AtharAppPage>\n</template>')

  fs.writeFileSync(filePath, finalContent)
  console.log('migrated:', rel)
}

for (const cfg of configs) {
  try {
    migrateFile(cfg.rel, cfg)
  } catch (e) {
    console.error('error', cfg.rel, e.message)
  }
}
