import i18n from '@/plugins/i18n/index.js';
import { can, canDoes } from '@layouts/plugins/casl';
import { toHijri } from "hijri-converter";



export const returnIdUserIfNotAdmin = (id = undefined) => {
  const userData = JSON.parse(localStorage.getItem('userData') || 'null')

  if(!can('admin', 'admin')){
    if(can('teacher', 'teacher') || (can('specialist', 'specialist') && !canDoes('social_specialist'))) {
      return userData.id;
    }
  }
  return undefined;
}

/////////////////////////////////////////////////////////////////////////////////
const hijri_months ={ 
    ar: {
        "01": "محرم",
        "02": "صفر",
        "03": "ربيع الأول",
        "04": "ربيع الآخر",
        "05": "جمادى الأولى",
        "06": "جمادى الآخرة",
        "07": "رجب",
        "08": "شعبان",
        "09": "رمضان",
        "10": "شوال",
        "11": "ذو القعدة",
        "12": "ذو الحجة"
      },
    en : {
        "01": "Muharram",
        "02": "Safar",
        "03": "Rabi' al-Awwal",
        "04": "Rabi' al-Thani",
        "05": "Jumada al-Awwal",
        "06": "Jumada al-Thani",
        "07": "Rajab",
        "08": "Sha'ban",
        "09": "Ramadan",
        "10": "Shawwal",
        "11": "Dhu al-Qi'dah",
        "12": "Dhu al-Hijjah"
      }
}

export const convertGregorianToHijri = (GregorianTDate) => {
  if(GregorianTDate){
    const [year, month, day] = GregorianTDate.split('T')[0].split('-').map(Number);
    const hijriDate = toHijri(year, month, day);
    return `${String(hijriDate.hd).padStart(2, '0')}-${hijri_months[i18n.global.locale.value][String(hijriDate.hm).padStart(2, '0')]}-${hijriDate.hy}`
  }
  return '';
    
}

export const isLogin = () => {

  const token = localStorage.getItem('accessToken');

  if(token)
    return true;

  return false;
    
}

export const search = (nameKey, myArray) => {
  for (let i=0; i < myArray.length; i++) {
      if (myArray[i].name === nameKey) {
          return myArray[i];
      }
  }
}

export const isUser = (id) => {

  const userData = JSON.parse(localStorage.getItem('userData') || 'null')

  if(userData.id == id)
    return true;

  return false;
    
}
