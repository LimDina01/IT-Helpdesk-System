    <!-- FULL-SCREEN IMAGE LIGHTBOX MODAL (TMA) -->
    <div x-show="showLightbox"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="showLightbox = false"
         class="fixed inset-0 z-[100] bg-black/95 backdrop-blur-md flex flex-col p-4" x-cloak>
        <div class="flex items-center justify-between text-white pb-3 max-w-md mx-auto w-full">
            <span class="text-xs font-semibold truncate max-w-[200px]" x-text="lightboxFilename"></span>
            <div class="flex items-center space-x-2">
                <a :href="lightboxDownloadUrl" download class="text-xs bg-white/20 hover:bg-white/30 text-white font-bold px-2.5 py-1 rounded-lg transition">
                    Download
                </a>
                <button type="button" @click="showLightbox = false" class="text-white hover:text-slate-300 text-2xl font-bold p-1">
                    &times;
                </button>
            </div>
        </div>
        <div class="flex-1 flex items-center justify-center max-w-md mx-auto w-full overflow-hidden">
            <img :src="lightboxUrl" :alt="lightboxFilename" class="max-h-[80vh] max-w-full rounded-xl shadow-2xl object-contain">
        </div>
    </div>
