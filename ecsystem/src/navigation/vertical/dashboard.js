export const buildDashboard = (userAbilities = []) => {
  const urls = []
  const abilities = Array.isArray(userAbilities) ? userAbilities : []

  if (abilities.some(item => item.action === 'admin' && item.subject === 'admin')) {
    urls.push({
      title: 'Dashboards',
      icon: { icon: 'tabler-smart-home' },
      to: 'dashboards-analytics',
      action: 'admin',
      subject: 'admin',
      badgeClass: 'bg-primary',
    })
  }
  else if (abilities.some(item => item.action === 'manager' && item.subject === 'manager')) {
    urls.push({
      title: 'Dashboards',
      icon: { icon: 'tabler-smart-home' },
      to: 'dashboards-analytics',
      action: 'manager',
      subject: 'manager',
      badgeClass: 'bg-primary',
    })
  }
  else if (abilities.some(item => item.action === 'specialist' && item.subject === 'specialist')) {
    urls.push({
      title: 'Dashboards',
      icon: { icon: 'tabler-smart-home' },
      to: 'dashboards-specialist',
      action: 'specialist',
      subject: 'specialist',
      badgeClass: 'bg-primary',
    })
  }
  else if (abilities.some(item => item.action === 'teacher' && item.subject === 'teacher')) {
    urls.push({
      title: 'Dashboards',
      icon: { icon: 'tabler-smart-home' },
      to: 'dashboards-teacher',
      action: 'teacher',
      subject: 'teacher',
      badgeClass: 'bg-primary',
    })
  }
  else if (abilities.some(item => item.action === 'parent' && item.subject === 'parent')) {
    urls.push({
      title: 'Dashboards',
      icon: { icon: 'tabler-smart-home' },
      to: 'dashboards-parent',
      action: 'parent',
      subject: 'parent',
      badgeClass: 'bg-primary',
    })
  }
  else if (abilities.some(item => item.action === 'default' && item.subject === 'default')) {
    urls.push({
      title: 'Dashboards',
      icon: { icon: 'tabler-smart-home' },
      to: 'dashboards-default',
      action: 'default',
      subject: 'default',
      badgeClass: 'bg-primary',
    })
  }

  return urls
}

export default buildDashboard
