import {
  resolveBackDestination,
  shouldShowBackNavigation,
} from '@/utils/navigation/backNavigation'

export function useBackNavigation() {
  const route = useRoute()
  const router = useRouter()

  const visible = computed(() => shouldShowBackNavigation(route))

  const destination = computed(() => (
    visible.value ? resolveBackDestination(route) : null
  ))

  const goBack = () => {
    const target = destination.value
    if (!target)
      return

    router.push(target)
  }

  return {
    visible,
    destination,
    goBack,
  }
}
