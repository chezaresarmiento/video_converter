<script setup>
import { useForm  } from '@inertiajs/vue3'
import { Inertia } from '@inertiajs/inertia'
import { ref, onMounted } from 'vue'
import { TrashIcon } from '@heroicons/vue/24/outline' // Import the Trash icon from v2



const fileError = ref('')
const youtubeLink = ref('')
const downloadFormat = ref('mp3')
var loader = ref(false)
var url_download = ref('')
const props = defineProps({
  
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
    downloads: {
        type: Array,
        required: true,
    },
});

function logout() {
    Inertia.post(route('logout'));
}

// Initialize the form with useForm
const form = useForm({
  name: null,
  video: null,
  video_url: null,
  video_format: 'mp3',
  youtubeLink: null, // Add youtubeLink to form
  downloadFormat: 'mp3', // Add downloadFormat to form
})

// Handle file upload and append to form data
const handleFileUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (file.type.startsWith('video/')) {
      fileError.value = ''
      form.video = file // Assign the file to the form
    } else {
      fileError.value = 'Please upload a valid video file.'
    }
  }
}

// Handle the form submission
function submit() {
  loader.value = true
  url_download.value = '' // Reset download link before the new request

  // Send the form data to the /videos/convert endpoint
  form.post('/videos/convert', {
    forceFormData: true, // Ensure FormData is used for file uploads
    preserveScroll: true, // Prevent scroll reset after the request
    onSuccess: () => {
      // Loader remains active until we get the event from Pusher
    },
    onError: () => {
      loader.value = false // Stop loader if there's an error
    }
  })
}

// Handle the YouTube download request
function download() {
  loader.value = true
  url_download.value = '' // Reset the download link before the new request

  // Set the form data for the YouTube link and format
  form.youtubeLink = youtubeLink.value 
  form.downloadFormat = downloadFormat.value

  // Post the form to the /videos/download endpoint but do NOT navigate
  // fetch('/videos/download', {
  //   method: 'POST',
  //   body: new FormData(document.querySelector('form')),
  // })
  form.post('/videos/download', {
    forceFormData: true,
    preserveScroll: true, // Prevents scroll reset after the request
    onSuccess: () => {
      // Loader remains active until we get the event from Pusher
      // loader.value = false
      //refresh the page
      // window.location.reload()
      
    },
    onError: () => {
      loader.value = false // Stop loader if there's an error
    }
  })
}

function deleteVideo(downloadId) {
  if (confirm('Are you sure you want to delete this video?')) {
    // Send a delete request to the server
    form.delete(`/videos/${downloadId}`, {
      onSuccess: () => {
        // Reload the page or remove the item from the downloads list
        window.location.reload(); // Reload the page after successful deletion
      },
      onError: () => {
        alert('Something went wrong while deleting the video.');
      }
    });
  }
} 

onMounted(() => {
  
    Echo.channel('conversions')
        .listen('ConversionCompleted', (e) => {
            console.log(e.url); // You should log the actual payload, like e.url
            loader.value = false
            url_download.value = e.url
            window.location.reload()
        });
});
</script>

<template>
  
  <div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    
    <div class="max-w-3xl mx-auto">

      <div v-if="canLogin" class="py-6 px-4 sm:px-6 lg:px-8 bg-indigo-600 text-white text-right text-2xl">
        <button @click="logout" class="btn">Logout</button>
      </div>
      <br>
      <br>  

      <h1 class="text-3xl font-bold text-center text-gray-900 mb-8">Video Tools</h1>
      
      <!-- Video Converter Section -->
      <section class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Video Converter</h2>
        <form @submit.prevent="submit" enctype="multipart/form-data">
          <div class="mb-4">
            <label for="videoName" class="block text-sm font-medium text-gray-700">Video Name</label>
            <input 
              type="text" 
              id="videoName" 
              v-model="form.name" 
              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              placeholder="Enter video name"
            >
          </div>
          
          <div class="mb-4">
            <label for="file" class="block text-sm font-medium text-gray-700">File</label>
            <input 
              type="file" 
              id="file" 
              @change="handleFileUpload" 
              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
            <p v-if="fileError" class="mt-2 text-sm text-red-600">{{ fileError }}</p>
          </div>
          <div class="mb-4">
            <label for="convertFormat" class="block text-sm font-medium text-gray-700">Output Format</label>
            <select 
              id="convertFormat" 
              v-model="form.video_format"
              class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
            >
              <!-- <option value="mp4">MP4</option> -->
              <option value="mp3" selected>MP3</option>
              <!-- <option value="avi">AVI</option>
              <option value="mov">MOV</option>
              <option value="wmv">WMV</option> -->
            </select>
          </div>
          <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Convert Video
          </button>
        </form>
      </section>
      
      <!-- YouTube Downloader Section -->
      <section class="bg-white shadow rounded-lg p-6">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">YouTube Downloader</h2>
        <form @submit.prevent="download" enctype="multipart/form-data">
          <div class="mb-4">
            <label for="youtubeLink" class="block text-sm font-medium text-gray-700">Paste your YouTube link here</label>
            <input 
              type="text" 
              id="youtubeLink" 
              v-model="youtubeLink"
              placeholder="https://www.youtube.com/watch?v=..."
              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
          </div>
          <div class="mb-4">
            <label for="downloadFormat" class="block text-sm font-medium text-gray-700">Choose the format of your download</label>
            <select 
              id="downloadFormat" 
              v-model="downloadFormat"
              class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
            >
              <option value="mp3">MP3</option>
              <option value="mp4">MP4</option>
              <option value="wav">WAV</option>
            </select>
          </div>
          <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Download
          </button>
          <br>
          <br>
          <div v-if="loader" class="flex justify-center items-center">
            <svg class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
            </svg>
            <p class="text-indigo-600 ml-3">Processing, please wait...</p>
          </div>

          <div v-if="url_download">
            <a :href="url_download" class="text-indigo-600 hover:underline" download>Download your file</a>
          </div>
        </form>
      </section>


      <section class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Your Downloads</h2>
        <div v-if="downloads.length > 0">
          <ul>
            <table class="min-w-full table-auto bg-white shadow-lg rounded-lg">
              <thead class="bg-gray-100 border-b-2 border-gray-200">
                <tr>
                  <th class="px-4 py-2 text-left text-gray-600 font-semibold">Video Title</th>
                  <th class="px-4 py-2 text-left text-gray-600 font-semibold">Download</th>
                  <th class="px-4 py-2 text-left text-gray-600 font-semibold">Video</th>
                  <th class="px-1 py-2 text-left text-gray-600 font-semibold">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="download in downloads" :key="download.id" class="hover:bg-gray-100 border-b border-gray-200">
                  <td class="px-4 py-2 text-gray-900">{{ download.video_title }}</td>
                  <td class="px-4 py-2">
                    <a :href="download.file_path" class="text-indigo-600 hover:text-indigo-900 underline" download>Download Again</a>
                  </td>
                  <td class="px-4 py-2">
                    <a :href="download.youtube_link" class="text-indigo-600 hover:text-indigo-900 underline" target="_blank">Watch Video</a>
                  </td>
                  <td class="px-1 py-2 text-center">
                    <!-- Trash icon for deleting -->
                    <TrashIcon class="w-6 h-6 text-red-600 hover:text-red-800 cursor-pointer" @click="deleteVideo(download.id)" />
                  </td>
                </tr>
              </tbody>
            </table>
  
          </ul>
        </div>
        <div v-else>
          <p class="text-gray-500">You haven't downloaded any videos yet.</p>
        </div>
      </section>
    </div>
  </div>
</template>
