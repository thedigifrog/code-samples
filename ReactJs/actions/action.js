import {ADD_TO_STORE} from '../constants';

// exporting the data
export  const add_to_store=(data)=>{
    return{
        type: ADD_TO_STORE,    // defines the type of data we are sending
        data: data     // sending the data
    }
}

// export const removeToCart = (data)=>{
//     return {
//         type: "REMOVE_TO_CART",
//         data: data
//     }
// }