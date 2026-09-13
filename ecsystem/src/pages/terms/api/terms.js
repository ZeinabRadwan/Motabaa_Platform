import axios from '@axios'
import { defineStore } from 'pinia'

export const termsServices = defineStore('terms', {
    state: ()=>{

        return {
            load: false,
            data:[],
            total:0
        }
    },
    // getters:{
    //     load:()=>{
    //         return this.load
    //     }
    // },
    actions:{
        async getTerms(option){
            this.load = true;
            let terms = await axios.post('terms', option)
            this.load = false;
            return  terms
        }
    },
});
