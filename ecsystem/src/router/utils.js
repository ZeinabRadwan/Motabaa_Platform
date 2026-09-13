/**
 * Return if user is logged in
 * This is completely up to you and how you store the token in the frontend application
 * e.g. If you are using cookies to store the application please update this function
 */
const readUserData = () => {
  try {
    return JSON.parse(localStorage.getItem('userData') || '{}')
  }
  catch (e) {
    return {}
  }
}

export const isUserLoggedIn = () => !!(localStorage.getItem('userData') && localStorage.getItem('accessToken'))

export const isAdmin = () => {
  const userData = readUserData()

  return !!(userData && userData.role === 'admin')
}

export const isInActiveCenter = () => {
  const userData = readUserData()
  const userCenter = localStorage.getItem('center')

  return !!(userCenter && userData.centers && userData.centers.find(item => item.id == userCenter && item.status == 1 && item.is_paid))
}

export const centerData = () => {
  const userData = readUserData()
  const userCenter = localStorage.getItem('center')

  return userData.centers ? userData.centers.find(item => item.id == userCenter) : ''
}
