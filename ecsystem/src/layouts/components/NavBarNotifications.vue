<script setup>
import { fetchUserNotifications, markUserNotificationRead } from '@/plugins/apis/notificationsRequest'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  /** VMenu location (e.g. sidebar footer uses `top start`). */
  location: {
    type: String,
    default: 'bottom end',
  },
  activatorVariant: {
    type: String,
    default: 'icon-btn',
  },
})

const router = useRouter()
const { locale } = useI18n({ useScope: 'global' })
const notifications = ref([])

const intlLocale = computed(() => (locale.value === 'ar' ? 'ar' : 'en'))

const formatNotificationTime = iso => {
  if (! iso)
    return ''
  try {
    return new Intl.DateTimeFormat(intlLocale.value, { dateStyle: 'medium' }).format(new Date(iso))
  }
  catch {
    return ''
  }
}

const mapApiNotification = row => ({
  id: row.id,
  title: row.title,
  subtitle: row.message,
  createdAt: row.created_at,
  time: formatNotificationTime(row.created_at),
  isSeen: Boolean(row.read_at),
  url: row.url,
})

watch(locale, () => {
  notifications.value = notifications.value.map(item => ({
    ...item,
    time: formatNotificationTime(item.createdAt),
  }))
})

const loadNotifications = () => {
  fetchUserNotifications({ per_page: 20 }).then(response => {
    const rows = response.data?.data ?? []
    notifications.value = rows.map(mapApiNotification)
  }).catch(() => {
    notifications.value = []
  })
}

onMounted(() => {
  const scheduleLoad = () => {
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden')
      return
    loadNotifications()
  }

  if (typeof window !== 'undefined' && window.requestIdleCallback)
    window.requestIdleCallback(scheduleLoad)
  else
    scheduleLoad()

  if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible')
        scheduleLoad()
    })
  }
})

const removeNotification = notificationId => {
  notifications.value = notifications.value.filter(item => item.id !== notificationId)
}

const markRead = notificationIds => {
  notificationIds.forEach(id => {
    const item = notifications.value.find(n => n.id === id)
    if (item && ! item.isSeen) {
      item.isSeen = true
      markUserNotificationRead(id).catch(() => {})
    }
  })
}

const markUnRead = () => {
  // Database notifications do not support unread; no-op for navbar UI.
}

const navigateFromNotification = notification => {
  const raw = notification?.url
  if (! raw)
    return

  try {
    const path = new URL(raw, window.location.origin).pathname
    router.push(path)
  }
  catch {
    if (raw.startsWith('/'))
      router.push(raw)
  }
}

const handleNotificationClick = notification => {
  if (! notification.isSeen)
    markRead([notification.id])
  navigateFromNotification(notification)
}
</script>

<template>
  <Notifications
    :notifications="notifications"
    :location="props.location"
    :activator-variant="props.activatorVariant"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>
