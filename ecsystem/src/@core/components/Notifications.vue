<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { avatarText } from '@core/utils/formatters'
import { mergeProps } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  notifications: {
    type: Array,
    required: true,
  },
  badgeProps: {
    type: null,
    required: false,
    default: undefined,
  },
  location: {
    type: null,
    required: false,
    default: 'bottom end',
  },
  /** `sidebar-toggle` matches athar-nav-prefs theme/language buttons in the vertical nav footer. */
  activatorVariant: {
    type: String,
    default: 'icon-btn',
    validator: v => ['icon-btn', 'sidebar-toggle'].includes(v),
  },
})

const emit = defineEmits([
  'read',
  'unread',
  'remove',
  'click:notification',
])

const { t } = useI18n({ useScope: 'global' })

const notificationTooltip = computed(() => t('navbar.notifications'))

const isAllMarkRead = computed(() => props.notifications.some(item => item.isSeen === false))

const markAllReadOrUnread = () => {
  const allNotificationsIds = props.notifications.map(item => item.id)
  if (!isAllMarkRead.value)
    emit('unread', allNotificationsIds)
  else
    emit('read', allNotificationsIds)
}

const totalUnseenNotifications = computed(() => {
  return props.notifications.filter(item => item.isSeen === false).length
})

const hasUnread = computed(() => props.notifications.some(n => !n.isSeen))
</script>

<template>
  <VMenu
    width="380px"
    :location="props.location"
    offset="14px"
    :close-on-content-click="false"
  >
    <template #activator="{ props: menuProps }">
      <VTooltip
        location="top"
        open-delay="200"
      >
        <template #activator="{ props: tipProps }">
          <button
            v-if="activatorVariant === 'sidebar-toggle'"
            id="notification-btn"
            type="button"
            class="athar-nav-prefs__toggle"
            v-bind="mergeProps(menuProps, tipProps)"
            :aria-label="notificationTooltip"
            :title="notificationTooltip"
          >
            <VBadge
              v-bind="props.badgeProps"
              :model-value="hasUnread"
              color="error"
              :content="totalUnseenNotifications"
              class="notification-badge"
            >
              <VIcon
                icon="tabler-bell"
                size="20"
              />
            </VBadge>
          </button>

          <IconBtn
            v-else
            id="notification-btn"
            v-bind="mergeProps(menuProps, tipProps)"
            :aria-label="notificationTooltip"
            :title="notificationTooltip"
          >
            <VBadge
              v-bind="props.badgeProps"
              :model-value="hasUnread"
              color="error"
              :content="totalUnseenNotifications"
              class="notification-badge"
            >
              <VIcon
                size="26"
                icon="tabler-bell"
              />
            </VBadge>
          </IconBtn>
        </template>
        <span>{{ notificationTooltip }}</span>
      </VTooltip>
    </template>

    <VCard class="d-flex flex-column">
      <!-- 👉 Header -->
      <VCardItem class="notification-section">
        <VCardTitle class="text-lg">
          {{ notificationTooltip }}
        </VCardTitle>

        <template #append>
          <IconBtn
            v-show="props.notifications.length"
            @click="markAllReadOrUnread"
          >
            <VIcon :icon="!isAllMarkRead ? 'tabler-mail' : 'tabler-mail-opened' " />

            <VTooltip
              activator="parent"
              location="start"
            >
              {{ !isAllMarkRead ? t('navbar.notifications_mark_all_unread') : t('navbar.notifications_mark_all_read') }}
            </VTooltip>
          </IconBtn>
        </template>
      </VCardItem>

      <VDivider />

      <!-- 👉 Notifications list -->
      <PerfectScrollbar
        :options="{ wheelPropagation: false }"
        style="max-block-size: 23.75rem;"
      >
        <VList class="notification-list rounded-0 py-0">
          <template
            v-for="(notification, index) in props.notifications"
            :key="notification.title"
          >
            <VDivider v-if="index > 0" />
            <VListItem
              link
              lines="one"
              min-height="66px"
              class="list-item-hover-class"
              @click="$emit('click:notification', notification)"
            >
              <template #prepend>
                <VListItemAction start>
                  <VAvatar
                    size="40"
                    :color="notification.color && notification.icon ? notification.color : undefined"
                    :image="notification.img || undefined"
                    :icon="notification.icon || undefined"
                    :variant="notification.img ? undefined : 'tonal' "
                  >
                    <span v-if="notification.text">{{ avatarText(notification.text) }}</span>
                  </VAvatar>
                </VListItemAction>
              </template>

              <VListItemTitle>{{ notification.title }}</VListItemTitle>
              <VListItemSubtitle>{{ notification.subtitle }}</VListItemSubtitle>
              <span class="text-xs text-disabled">{{ notification.time }}</span>

              <template #append>
                <div class="d-flex flex-column align-center gap-4">
                  <VBadge
                    dot
                    :color="!notification.isSeen ? 'primary' : '#a8aaae'"
                    :class="`${notification.isSeen ? 'visible-in-hover' : ''} ms-1`"
                    @click.stop="$emit(notification.isSeen ? 'unread' : 'read', [notification.id])"
                  />

                  <div style="block-size: 28px; inline-size: 28px;">
                    <IconBtn
                      size="small"
                      class="visible-in-hover"
                      @click="$emit('remove', notification.id)"
                    >
                      <VIcon
                        size="20"
                        icon="tabler-x"
                      />
                    </IconBtn>
                  </div>
                </div>
              </template>
            </VListItem>
          </template>

          <VListItem
            v-show="!props.notifications.length"
            class="text-center text-medium-emphasis"
            style="block-size: 56px;"
          >
            <VListItemTitle>{{ t('navbar.notifications_empty') }}</VListItemTitle>
          </VListItem>
        </VList>
      </PerfectScrollbar>

      <VDivider />

      <VCardActions
        v-show="props.notifications.length"
        class="notification-footer"
      >
        <VBtn block>
          {{ t('navbar.notifications_view_all') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VMenu>
</template>

<style lang="scss">
.notification-section {
  padding: 14px !important;
}

.notification-footer {
  padding: 6px !important;
}

.list-item-hover-class {
  .visible-in-hover {
    display: none;
  }

  &:hover {
    .visible-in-hover {
      display: block;
    }
  }
}

.notification-list.v-list {
  .v-list-item {
    border-radius: 0 !important;
    margin: 0 !important;
  }
}

.notification-badge {
  .v-badge__badge {
    /* stylelint-disable-next-line liberty/use-logical-spec */
    min-width: 18px;
    padding: 0;
    block-size: 18px;
  }
}
</style>
