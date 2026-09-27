const HIDE_CLASSES = ['athar-glass-loader--hide', 'athar-loader--hide']

let dismissed = false

export function dismissInitialLoader() {
  if (dismissed)
    return

  const el = document.getElementById('loading-bg')

  if (!el || el.dataset.dismissed === '1') {
    dismissed = true

    return
  }

  dismissed = true
  el.dataset.dismissed = '1'
  HIDE_CLASSES.forEach(cls => el.classList.add(cls))
  document.documentElement.classList.remove('athar-initial-loading')
  document.documentElement.style.overflow = ''
  document.body.style.overflow = ''

  window.setTimeout(() => {
    el.remove()
  }, 400)
}

if (typeof window !== 'undefined')
  window.dismissInitialLoader = dismissInitialLoader // overrides index.html stub with module guard
