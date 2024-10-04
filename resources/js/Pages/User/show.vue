<script setup>
import { Head } from '@inertiajs/vue3';
 
const props = defineProps(['user']);
</script>
 
<template>
    <Head title="User Profile" />
 
    <Layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Profile
            </h2>
        </template>
 
        <div class="text-3xl font-bold underline ">
            Hello, {{ user.name }}
        </div>

        <div class="columns-2">
            <p>Well, let me tell you something, ...</p>
            <p class="">Sure, go ahead, laugh...</p>
            <p>Maybe we can live without...</p>
            <p>Look. If you think this is...</p>
        </div>

    </Layout>
</template>