<template>

<div class="flex flex-col">
    <div class="md:max-w-sm w-full space-y-8 md:border md:border-gray md:rounded-3xl md:shadow-cm p-8 ml-auto mr-auto">
        <div>
            <img class="mx-auto w-auto -mt-4" :src="logopath" alt="Gufi">
            <h2 class="mt-2 text-center text-base font-bold text-pink">{{ ('Select Account Type') }}</h2>
            <p class="mt-2 px-4 text-gray-light text-center text-xs">{{ ('Do not worry, this can be changed later') }}</p>
            
        </div>
        <!-- Error msg in blank input field -->
         <span id="checkerror" v-if="errormsg">Role is required</span>
         <!-- End -->
        <form class="space-y-6">
           
            <div class="grid sm:grid-cols-2 gap-4 pb-6 place-items-center radiobtns">
                <div class="text-center w-full">
                    <h1 class="text-xs font-bold text-pink hidden sm:block">{{ ('Client') }}</h1>
                    <label for="client" class="flex rounded-3xl shadow-cs p-4 mt-3 border-2 border-pink relative">
                        <input type="radio" name="accountType" class="opacity-0 z-10 absolute inset-0" id="client" value="Client" v-model="radiobtn">
                        <div class="grid grid-cols-2 sm:grid-cols-1 gap-4 justify-between items-center w-full">
                            <img :src="path" class="mx-auto" alt="Client">
                            <h1 class="text-xs font-bold text-pink sm:hidden">{{ ('Client') }}</h1>
                        </div>
                        </label>
                    </div>
                    
                
                <div class="text-center w-full">
                    <h1 class="text-xs font-bold text-pink hidden sm:block">{{ ('Designer') }}</h1>
                    <label for="designer" class="flex rounded-3xl shadow-cs p-4 mt-3 relative">
                        <input type="radio" name="accountType" class="opacity-0 z-10 absolute inset-0 " id="designer" value="Designer" v-model="radiobtn">
                        <div class="grid grid-cols-2 sm:grid-cols-1 gap-4 justify-between items-center w-full">
                            <img :src="path2" class="mx-auto" alt="Designer">
                            <h1 class="text-xs font-bold text-pink sm:hidden">{{ ('Designer') }}</h1>
                        </div>
                        </label>
                    </div>
            </div>
        
            <div class="space-y-4">
                <button class="bg-pink-500 hover:bg-pink-700 text-white font-bold py-2 px-4 rounded-full" v-on:click="next_three"> Next </button>
                <button class="bg-pink-500 hover:bg-pink-700 text-white font-bold py-2 px-4 rounded-full" v-on:click="back_three"> Back</button>
            </div>
        </form>
    </div>
</div>

</template>

<script>

export default {
    data(){
        return{
            path: '/images/client.png',
            path2: '/images/designer.png',
            logopath: '/images/logo-pink.png',
            radiobtn: '',
            errormsg: false
        }
    },
    methods:{
        // Next Button Functionality
        next_three(e){
                e.preventDefault();
                if(this.radiobtn == ''){
                    this.errormsg = true;
                }
                else{
                    this.errormsg = false;
                    this.$router.push({path: '/choose_expertise'})
                }
        },
        // Back Button Functionality
        back_three(e){
            e.preventDefault();
            this.$router.go(-1)

        }
    }
}
</script>