<dialog id="confirm-modal" class="w-full max-w-lg overflow-hidden rounded-[28px] border border-gray-200 bg-white p-0 text-gray-900 shadow-theme-xl backdrop:bg-gray-900/60 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
    <div class="p-6 sm:p-7">
        <div class="flex items-start gap-4">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-warning-500 text-white shadow-theme-xs">
                <i class="ti ti-alert-triangle text-xl"></i>
            </span>
            <div class="min-w-0">
                <h2 id="confirm-modal-title" class="text-xl font-semibold tracking-tight">Confirm action</h2>
                <p id="confirm-modal-message" class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">Please confirm this action.</p>
            </div>
        </div>

        <form id="confirm-modal-form" method="POST" class="mt-6">
            @csrf
            <div id="confirm-modal-hidden-payload"></div>
            <div class="flex flex-wrap justify-end gap-3">
                <button type="button" data-close-confirm-modal class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                    Cancel
                </button>
                <button id="confirm-modal-submit" type="submit" class="inline-flex items-center justify-center rounded-xl bg-error-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-error-600">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</dialog>
