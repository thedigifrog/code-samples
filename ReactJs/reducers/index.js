import users from "./reducer";

import {combineReducers} from 'redux';


//  Combining all the reducers
export default combineReducers({
    users,
})