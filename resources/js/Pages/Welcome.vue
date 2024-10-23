<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    auth: Object,
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
});

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <Head title="Welcome" />
    <div class="bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <div
            class="relative min-h-screen flex flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white"
        >
            <div class="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                <header class="grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3">
                    <div class="flex lg:justify-center lg:col-start-2">
                        <h1 class="text-3xl font-bold text-[#FF2D20]">YouTube Downloader & Converter</h1>
                    </div>
                </header>

                <main class="mt-6">
                    <div class="text-center">
                        <h2 class="text-2xl font-semibold text-black dark:text-white">Welcome to YouTube Downloader & Converter!</h2>
                        <p class="mt-4 text-lg text-black/70 dark:text-white/70">
                            You can download or convert YouTube videos easily. Once downloaded, the videos will be saved in your library where you can view or re-download them at any time.
                        </p>
                        <div class="mt-10 flex">
                            <div class="w-1/2 9-4" v-if="canLogin">
                                <Link
                                    v-if="$page.props.auth.user"
                                    :href="route('dashboard')"
                                    class="rounded-md bg-[#FF2D20] text-white px-6 py-3 transition hover:bg-[#e62c1d] focus:outline-none"
                                >
                                        Dashboard
                                </Link>
                            </div>

                            

                            <div v-if="canRegister" class="w-1/2 p-4">
                                <Link
                                        :href="route('register')"
                                        class="rounded-md bg-[#FF2D20] text-white px-6 py-3 transition hover:bg-[#e62c1d] focus:outline-none"
                                    >
                                        Register
                                </Link>
                            </div>

                            <div v-if="auth.user">

                            </div>

                            <div class="w-1/2 p-4" v-else>
                                <Link
                                        :href="route('login')"
                                        class="rounded-md bg-[#FF2D20] text-white px-6 py-3 transition hover:bg-[#e62c1d] focus:outline-none"
                                    >
                                    Login
                                </Link>
                            </div>
                            

                            
                        
                        </div>
                    </div>

                    
                </main>

                <footer class="py-16 text-center text-sm text-black dark:text-white/70">
                    Cesar Sarmiento Built with Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})
                </footer>
            </div>
        </div>
    </div>
</template>
