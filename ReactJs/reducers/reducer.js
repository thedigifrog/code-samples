import {ADD_TO_STORE} from '../constants';



// defining the initial state
const initialState = {
};
export default function users(state = initialState, action) {
    switch (action.type) {
        case ADD_TO_STORE:
            return {
                ...state,
                 CardData: action.data //receiving the data from action
            }
        // break;
        default:
            return state
    }
}