export default [
  // { heading: '' },
  {
    title: 'system_settings',
    icon: { icon: 'tabler-building-skyscraper' },
    children: [
      { title: 'Centers', to: { name: 'centers-list' } , action:'access_centers', subject:'access_centers'},
      { title: 'centers.payments', to: { name: 'centers-payments-list' } , action:'access_centers', subject:'access_centers'},
      { title: 'centers.users', to: { name: 'centers-users-list' } , action:'access_centers', subject:'access_centers'},
      { title: 'Roles & Permissions', to: { name: 'roles-list' }, action:'access_roles', subject:'access_roles' },
    ],
  }
]
