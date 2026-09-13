/**
 * plugins/webfontloader.js
 *
 * webfontloader documentation: https://github.com/typekit/webfontloader
 */
export async function loadFonts() {
  const webFontLoader = await import(/* webpackChunkName: "webfontloader" */ 'webfontloader')

  webFontLoader.load({
    custom: {
      families: ['DroidArabicKufiRegular'],
      urls: ['https://fontlibrary.org//face/droid-arabic-kufi'],
    },
    google: {
      // families: ['Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap'],
      // families: ['Rubik:ital,wght@0,300;0,400;0,500;0,700;0,800;0,900;1,300;1,400;1,500;1,700;1,800;1,900&display=swap'],
      families: ['Noto+Kufi+Arabic:wght@300;400;500;600;700&display=swap'],
      // families: ['Baloo+Bhaijaan+2:wght@400;500;600;700;800&display=swap'],

    },
  })
}
