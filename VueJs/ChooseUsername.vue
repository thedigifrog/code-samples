<template>
  <div class="flex flex-col">
    <div
      class=" md:max-w-sm w-full space-y-8 md:border md:border-gray md:rounded-3xl md:shadow-cm
        p-8 ml-auto mr-auto" >
      <div>
        <img class="mx-auto w-auto -mt-4" :src="path" alt="Gufi" />
        <h2 class="mt-2 text-center text-base font-bold text-pink">
          {{ "Choose a username" }}
        </h2>
        <p class="mt-2 px-4 text-gray-light text-center text-xs">
          {{ "Please note that username cannot be changed once chose." }}
        </p>
      </div>
      <form class="mt-8 space-y-6">
        <div class="rounded-md shadow-sm space-y-4">
          <div>
            <input type="text" name="useranme" id="useranme" class="w-full rounded-full text-center"
               autocomplete="text" placeholder="Username" autofocus v-model="username"
            />
          </div>
          <span id="CreateSpan" v-if="msg">Username is required</span>
        </div>

        <div class="pt-4 pb-12">
          <p class="text-gray-light mb-4">Suggestions</p>
          <!-- Suggestions Name  -->
          <div v-if="email" >
          <ul id="ul" @click="handleUser($event)" v-for="(item,i) in user" v-bind:key="i">  
              <li class="p-1"> {{item}} </li>
          </ul>
          </div>
          <!-- End -->
        </div>

        <div class="space-y-4 flex flex-col w-10/12 item-center">
          <button class="bg-pink-500 hover:bg-pink-700 text-white font-bold py-2 px-4 rounded-full" v-on:click="next_two"> Next </button>
          <button class="bg-pink-500 hover:bg-pink-700 text-white font-bold py-2 px-4 rounded-full" v-on:click="back_two"> Back </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      path: "/images/logo-pink.png",
      username: '',
      msg: false,
      user: [],
      email: false
    };
  },

  // Watch the input field
  watch: {
    username(value) {
      if (value.length == 0) {
        this.msg = true;
      }
      else{
        this.msg = false
      }

    },
  },

  // Random Username Generate Function
  mounted(){
    let email = (this.$route.query.email);
    if(email){
    this.email = true
    let char = (email.substring(0,email.lastIndexOf("@")));
    var min = 500;
    var max = 1500;

    //loop to generate random number
      for(let i=0; i<4; i++){
        var value = Math.floor(Math.random() * (max - min + i)) + min
        // Push the character from email and random number
        this.user.push(`${char}${value}`);
      }
    }
  },
  // End

  methods:{
    // Next button functionality
    next_two(e){
      e.preventDefault();
      if(this.username.length == 0){
        this.msg = true
      }
      else{
        this.$router.push({path: '/select_account'})
      }
    //  Back button functionality
    },
    back_two(e){
       e.preventDefault();
       this.$router.go(-1)
    },
    // Click on Suggestion functionality
    handleUser(e){
      e.preventDefault();
      if (e.target.tagName == ("LI"));
      var innerData = e.target.innerHTML
      this.username = innerData;
    }
  }
};
</script>