<template>
    <div>
      <h1>Uploaded Video</h1>
      <p v-if="videos.video">Video Path: {{ videos.video }}</p>
      
      <!-- Audio player -->
      <audio v-if="videos.video" controls>
        <source :src="videoUrl" type="audio/mpeg">
        Your browser does not support the audio element.
      </audio>
  
      <!-- Downloadable link -->
      <div v-if="videos.video" style="margin-top: 20px;">
        <a :href="videoUrl" :download="getFileName(videos.video)" class="download-link">
          Download MP3
        </a>
      </div>
    </div>
  </template>
  
  <script>
  export default {
    props: {
      videos: {
        type: Object,
        required: true,
      },
    },
    computed: {
      videoUrl() {
        return this.videos.video ? `/videos/${this.getFileName(this.videos.video)}` : '';
      }
    },
    methods: {
      getFileName(fullPath) {
        return fullPath.split('/').pop();
      }
    }
  };
  </script>
  
  <style>
  .download-link {
    padding: 10px 15px;
    background-color: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 5px;
  }
  
  .download-link:hover {
    background-color: #2980b9;
  }
  </style>
  