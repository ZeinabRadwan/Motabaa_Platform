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
      {
        identifier: 'goals-assessments',
        title: 'Assessment',
        to: { name: 'goals-assessments' },
        action: 'access_education-evaluations',
        subject: 'access_education-evaluations',
        actionsAny: ['access_education-evaluations', 'access_parent-education-evaluations'],
      },
    ],
  },
  {
    identifier: 'support-services',
    title: 'Support Services',
    icon: { icon: 'tabler-writing' },
    children: [
      { identifier: 'services', title: 'Treatment plans', to: { name: 'services-list' }, action: 'access_treatment-goals', subject: 'access_treatment-goals' },
      { identifier: 'services-sessions', title: 'Sessions', to: { name: 'services-sessions' }, action: 'access_treatment-sessions', subject: 'access_treatment-sessions' },
      {
        identifier: 'services-assessments',
        title: 'Assessment',
        to: { name: 'services-assessments' },
        action: 'access_treatment-evaluations',
        subject: 'access_treatment-evaluations',
        actionsAny: ['access_treatment-evaluations', 'access_parent-treatment-evaluations'],
      },
    ],
  },
  {
    identifier: 'independent_goals',
    title: 'independent.independent_skills',
    icon: { icon: 'tabler-notes' },
    children: [
      { identifier: 'independent_goals-list', title: 'goals.skills', to: { name: 'independent_goals-list' }, action: 'access_independent-goals', subject: 'access_independent-goals' },
      { identifier: 'independent_goals-sessions', title: 'Sessions', to: { name: 'independent_goals-sessions' }, action: 'access_independent-sessions', subject: 'access_independent-sessions' },
      {
        identifier: 'independent_goals-assessments',
        title: 'Assessment',
        to: { name: 'independent_goals-assessments' },
        action: 'access_independent-evaluations',
        subject: 'access_independent-evaluations',
        actionsAny: ['access_independent-evaluations', 'access_parent-independent-evaluations'],
      },
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
    identifier: 'hr_my_attendance',
    title: 'hr.my_attendance.title',
    icon: { icon: 'tabler-map-pin-check' },
    action: 'apply_hr-self-attendance',
    subject: 'apply_hr-self-attendance',
    to: { name: 'hr-my-attendance' },
  },
  {
    identifier: 'hr_my_leave',
    title: 'hr.my_leave.title',
    icon: { icon: 'tabler-beach' },
    action: 'apply_hr-self-leave',
    subject: 'apply_hr-self-leave',
    to: { name: 'hr-my-leave' },
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
      { identifier: 'hr_employees', title: 'hr.employees.title', to: { name: 'hr-employees-list' }, action: 'access_hr-employees', subject: 'access_hr-employees' },
      { identifier: 'hr_organization', title: 'hr.organization.title', to: { name: 'hr-organization' }, action: 'access_hr-organization', subject: 'access_hr-organization' },
      { identifier: 'hr_attendance_setup', title: 'hr.attendance_setup.title', to: { name: 'hr-attendance-setup' }, action: 'access_hr-attendance-setup', subject: 'access_hr-attendance-setup' },
      { identifier: 'hr_attendance_records', title: 'hr.attendance_records.title', to: { name: 'hr-attendance-records' }, action: 'access_hr-attendance-records', subject: 'access_hr-attendance-records' },
      { identifier: 'hr_location_monitoring', title: 'hr.location_monitoring.title', to: { name: 'hr-location-monitoring' }, action: 'access_hr-location-monitoring', subject: 'access_hr-location-monitoring' },
      {
        identifier: 'hr_attendance_processing',
        title: 'hr.attendance_processing.title',
        to: { name: 'hr-attendance-processing' },
        action: 'access_hr-attendance-processing',
        subject: 'access_hr-attendance-processing',
        actionsAny: ['access_hr-attendance-processing', 'edit_hr-attendance-processing', 'admin_hr-attendance-processing'],
      },
      {
        identifier: 'hr_leave',
        title: 'hr.leave.title',
        to: { name: 'hr-leave' },
        action: 'access_hr-leave',
        subject: 'access_hr-leave',
        actionsAny: ['access_hr-leave', 'edit_hr-leave', 'admin_hr-leave', 'apply_hr-leave'],
      },
      {
        identifier: 'hr_additional_days',
        title: 'hr.additional_days.title',
        to: { name: 'hr-additional-days' },
        action: 'access_hr-additional-days',
        subject: 'access_hr-additional-days',
        actionsAny: ['access_hr-additional-days', 'edit_hr-additional-days', 'admin_hr-additional-days', 'apply_hr-additional-days'],
      },
      {
        identifier: 'hr_approvals',
        title: 'hr.approvals.title',
        to: { name: 'hr-approvals-list' },
        action: 'access_hr-approvals',
        subject: 'access_hr-approvals',
        actionsAny: ['access_hr-approvals', 'apply_hr-approvals', 'edit_hr-approvals', 'apply_hr-leave', 'apply_hr-self-leave', 'apply_hr-additional-days'],
      },
      {
        identifier: 'hr_settings',
        title: 'hr.settings.page_title',
        to: { name: 'hr-settings' },
        action: 'access_hr-settings',
        subject: 'access_hr-settings',
        actionsAny: ['access_hr-settings', 'access_hr-approvals', 'edit_hr-approvals'],
      },
      { identifier: 'hr_audit', title: 'hr.audit.title', to: { name: 'hr-audit-logs-list' }, action: 'access_hr-audit', subject: 'access_hr-audit' },
    ],
  },
  {
    identifier: 'meeting-rooms',
    title: 'Meeting rooms',
    icon: { icon: 'tabler-door' },
    action: 'access_meetings',
    subject: 'access_meetings',
    meetingRoomNav: true,
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
    icon: { icon: 'tabler-building-skyscraper' },
    children: [
      { identifier: 'centers', title: 'Centers', to: { name: 'centers-list' }, action: 'access_centers', subject: 'access_centers' },
      { identifier: 'centers-payments', title: 'centers.payments', to: { name: 'centers-payments-list' }, action: 'access_centers', subject: 'access_centers' },
      { identifier: 'centers-users', title: 'centers.users', to: { name: 'centers-users-list' }, action: 'access_centers', subject: 'access_centers' },
      { identifier: 'roles', title: 'Roles & Permissions', to: { name: 'roles-list' }, action: 'access_roles', subject: 'access_roles' },
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
      { identifier: 'media_attachment_audit', title: 'Media attachment audit', to: { name: 'logs-media-attachments-list' }, action: 'access_logs', subject: 'access_logs' },
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
