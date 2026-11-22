<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#162338">
    <title>Saved readings — JK Michaels</title>
    @vite(['resources/css/app.css', 'resources/js/offline.js'])
</head>
<body class="min-h-screen bg-surface-50 text-surface-900">
    <main class="mx-auto max-w-4xl px-5 py-10 sm:py-16">
        <p class="text-sm font-semibold text-primary-700">JK MICHAELS · YOUR LEARNING LIBRARY</p>
        <h1 class="mt-3 text-3xl font-bold">Saved readings</h1>
        <p class="mt-3 max-w-2xl text-surface-600">Read material saved in this tab for up to 24 hours. Signing out or closing the tab removes access. Video, audio, documents and assessments require a connection.</p>
        <nav class="my-6 flex flex-wrap gap-3" aria-label="Offline actions">
            <a href="/downloads" class="rounded-lg border border-surface-300 bg-white px-4 py-2 font-medium">Return to workspace</a>
            <button id="sync-readings" class="rounded-lg bg-surface-900 px-4 py-2 font-medium text-white">Sync reading progress</button>
        </nav>
        <p id="offline-status" role="status" class="mb-5 rounded-xl border border-surface-200 bg-white p-4 text-sm"></p>
        <section id="saved-readings" aria-label="Your saved readings" class="space-y-4"></section>
        <article id="reading" class="mt-8 hidden rounded-2xl border border-surface-200 bg-white p-6 sm:p-10">
            <h2 id="reading-title" tabindex="-1" class="mb-6 text-2xl font-bold"></h2>
            <div id="reading-content" class="lesson-prose"></div>
            <button id="reading-complete" class="mt-8 rounded-lg bg-primary-700 px-4 py-3 font-semibold text-white">Record reading completion</button>
        </article>
    </main>
</body>
</html>
