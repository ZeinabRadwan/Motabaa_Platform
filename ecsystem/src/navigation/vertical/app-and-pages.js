export const APP_AND_PAGES_TEMPLATE = [
  // { heading: '' },
  {
    identifier: 'cases-menu',
    title: 'Cases',
    icon: { icon: 'tabler-mood-boy' },
    children: [
      { identifier: 'cases-add', title: 'Register', to: { name: 'cases-add' }, action: 'edit_cases', subject: 'edit_cases' },
      { identifier: 'cases-assessments', title: 'Assessment', to: { name: 'cases-assessments' }, action: 'access_assessments_cases', subject: 'access_assessments_cases' },
      { identifier: 'cases', title: 'Show Cases', to: { name: 'cases-list' }, action: 'access_cases_mine', subject: 'access_cases_mine', actionsAny: ['access_cases_mine', 'access_cases_all', 'access_cases', 'admin_cases'] },
    ],
  },
  {
    identifier: 'qualifying-programme',
    title: 'Qualifying programme',
    icon: { icon: 'tabler-notes' },
    children: [
      { identifier: 'goals', title: 'Individual Plan', to: { name: 'goals-list' }, action: 'access_education-goals', subject: 'access_education-goals' },
      { identifier: 'goals-sessions', title: 'Sessions', icon: { icon: 'tabler-analyze' }, to: { name: 'goals-sessions' }, action: 'access_education-sessions', subject: 'access_education-sessions' },
      { identifier: 'goals-assessments', title: 'Assessment', to: { name: 'goals-assessments' }, action: 'access_education-evaluations', subject: 'access_education-evaluations' },
    ],
  },
  {
    identifier: 'support-services',
    title: 'Support Services',
    icon: { icon: 'tabler-writing' },
    children: [
      { identifier: 'services', title: 'Treatment plans', to: { name: 'services-list' }, action: 'access_treatment-goals', subject: 'access_treatment-goals' },
      { identifier: 'services-sessions', title: 'Sessions', to: { name: 'services-sessions' }, action: 'access_treatment-sessions', subject: 'access_treatment-sessions' },
      { identifier: 'services-assessments', title: 'Assessment', to: { name: 'services-assessments' }, action: 'access_treatment-evaluations', subject: 'access_treatment-evaluations' },
    ],
  },
  {
    identifier: 'independent_goals',
    title: 'independent.independent_skills',
    icon: { icon: 'tabler-notes' },
    children: [
      { identifier: 'independent_goals-list', title: 'goals.skills', to: { name: 'independent_goals-list' }, action: 'access_independent-goals', subject: 'access_independent-goals' },
      { identifier: 'independent_goals-sessions', title: 'Sessions', to: { name: 'independent_goals-sessions' }, action: 'access_independent-sessions', subject: 'access_independent-sessions' },
      { identifier: 'independent_goals-assessments', title: 'Assessment', to: { name: 'independent_goals-assessments' }, action: 'access_independent-evaluations', subject: 'access_independent-evaluations' },
    ],
  },
  {
    identifier: 'attendances',
    title: 'Attendance',
    icon: { icon: 'tabler-calendar-check' },
    action: 'access_attendance',
    subject: 'access_attendance',
    to: { name: 'attendances-list' },
  },
  {
    identifier: 'parents',
    title: 'Parents',
    icon: { icon: 'tabler-users-group' },
    action: 'access_parents',
    subject: 'access_parents',
    to: { name: 'parents-list' },
  },
  {
    identifier: 'employee_affairs',
    title: 'employee_affairs.menu',
    icon: { icon: 'tabler-briefcase' },
    children: [
      { identifier: 'employee_affairs_dashboard', title: 'employee_affairs.dashboard', to: { name: 'employee_affairs' }, action: 'access_users', subject: 'access_users' },
      { identifier: 'users', title: 'Career staff', to: 'user-list', action: 'access_users', subject: 'access_users' },
      { identifier: 'employees_attendance', title: 'Staff attendance', to: { name: 'employees_attendance-list' }, action: 'access_employees-attendance', subject: 'access_employees-attendance' },
      { identifier: 'employee_leaves', title: 'employee_leaves.leaves', to: { name: 'employee_leaves-list' }, action: 'access_users', subject: 'access_users' },
    ],
  },
  {
    identifier: 'meeting-rooms',
    title: 'Meeting rooms',
    icon: { icon: 'tabler-door' },
    action: 'access_meetings',
    subject: 'access_meetings',
    to: { name: 'meeting-rooms-list' },
  },
  {
    identifier: 'center-activities',
    title: 'center_activities.menu',
    icon: { icon: 'tabler-calendar-event' },
    action: 'access_center-activities',
    subject: 'access_center-activities',
    to: { name: 'center-activities-list' },
  },
  {
    identifier: 'study-fees',
    title: 'Study fees',
    icon: { icon: 'tabler-report-money' },
    children: [
      { identifier: 'fees', title: 'payments.fees', to: { name: 'fees-list' }, action: 'access_study-fees', subject: 'access_study-fees' },
      { identifier: 'payments', title: 'payments.payments', to: { name: 'payments-list' }, action: 'access_study-fees', subject: 'access_study-fees' },
    ],
  },
  {
    identifier: 'system_settings',
    title: 'system_settings',
    icon: { icon: 'tabler-settings' },
    children: [
      { identifier: 'centers', title: 'Centers', icon: { icon: 'tabler-building' }, to: { name: 'centers-list' }, action: 'access_centers', subject: 'access_centers' },
      { identifier: 'centers-payments', title: 'centers.payments', icon: { icon: 'tabler-credit-card' }, to: { name: 'centers-payments-list' }, action: 'access_centers', subject: 'access_centers' },
      { identifier: 'centers-users', title: 'centers.users', icon: { icon: 'tabler-users' }, to: { name: 'centers-users-list' }, action: 'access_centers', subject: 'access_centers' },
      { identifier: 'roles', title: 'Roles & Permissions', icon: { icon: 'tabler-shield-lock' }, to: { name: 'roles-list' }, action: 'access_roles', subject: 'access_roles' },
    ],
  },
  {
    identifier: 'settings',
    title: 'Settings',
    icon: { icon: 'tabler-settings' },
    children: [
      { identifier: 'classes', title: 'Qualifying classes', to: { name: 'classes-list' }, action: 'access_qualifying-classes', subject: 'access_qualifying-classes' },
      { identifier: 'scales', title: 'Scales', to: { name: 'scales-list' }, action: 'access_scales', subject: 'access_scales' },
      { identifier: 'plans', title: 'Operational plan', to: { name: 'plans-list' }, action: 'access_operation-plans', subject: 'access_operation-plans' },
      { identifier: 'logs', title: 'Logs', to: { name: 'logs-list' }, action: 'access_logs', subject: 'access_logs' },
      { identifier: 'questionnaires', title: 'Questionnaires', to: { name: 'questionnaires-list' }, action: 'access_questionnaires', subject: 'access_questionnaires' },
    ],
  },
]

export const buildAppAndPages = (center, isUserAdmin) => {
  let routes = JSON.parse(JSON.stringify(APP_AND_PAGES_TEMPLATE))

  if (center) {
    if (Number(center.package_id) == 2) {
      const settingsMenu = routes.find(item => item.identifier === 'settings')
      if (settingsMenu)
        settingsMenu.children = settingsMenu.children.filter(item => item.identifier != 'plans' && item.identifier != 'questionnaires')

      const hrMenu = routes.find(item => item.identifier === 'employee_affairs')
      if (hrMenu)
        hrMenu.children = hrMenu.children.filter(item => item.identifier != 'employees_attendance')

      routes = routes.filter(item => item.identifier != 'independent_goals' && item.identifier != 'attendances' && item.identifier != 'study-fees')
    }
    else if (Number(center.package_id) == 3) {
      const settingsMenu = routes.find(item => item.identifier === 'settings')
      if (settingsMenu)
        settingsMenu.children = settingsMenu.children.filter(item => item.identifier != 'plans' && item.identifier != 'questionnaires')

      const hrMenu = routes.find(item => item.identifier === 'employee_affairs')
      if (hrMenu)
        hrMenu.children = hrMenu.children.filter(item => item.identifier != 'employees_attendance')
    }
  }
  else if (isUserAdmin) {
    routes = routes.filter(item => item.identifier == 'system_settings')
  }
  else {
    routes = routes.filter(item => item.identifier == '')
  }

  return routes
}

export default buildAppAndPages
