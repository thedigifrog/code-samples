import React, { useState } from "react";
import '../index.css';
import CardDesign from "./CardDesign";
import Menu from '../APIfile/MenuItems';

const Restaurant = () => {

    const [menuData, setMenuData] = useState(Menu);   // set data of menu file to menuData variable

    const filterItem = (category) => {
        const updatedList = Menu.filter((curElem) => { 
            return curElem.category === category;   // matching the defined data with API
        });
        setMenuData(updatedList);  // Set updated data to updateList variable
    };
    return (
        <>
        {/* Navbar */} 
            <nav>
                <div className="butt">
                    <button onClick={() => setMenuData(Menu)}> Home  </button>
                    <button onClick={() => filterItem("breakfast")}> Breakfast  </button>
                    <button onClick={() => filterItem("lunch")}> Lunch  </button>
                    <button onClick={() => filterItem("snacks")}> Snaks  </button>
                    <button onClick={() => filterItem("dinner")}> Dinner  </button>

                </div>
            </nav>
        {/* End */}

        {/* Mapping the menudata to get the element and key */}
            {
                menuData.map((curElem, i) => {
                    return   <CardDesign menuData={curElem} key={i}/>  //sending the data to cardDesign component
                  
                })
            };
            {/* End */}
        </>
    )
}
export default Restaurant;
