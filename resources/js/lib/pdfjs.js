let loading = null;

/**
 * Lazily load PDF.js with one shared worker for the whole app. The worker is
 * bundled as a classic .js file so servers without an .mjs MIME type still
 * serve it with a JavaScript content type.
 */
export function loadPdfjs() {
    loading ??= (async () => {
        const pdfjs = await import('pdfjs-dist');
        const { default: PdfWorker } = await import('pdfjs-dist/build/pdf.worker.min.mjs?worker');
        pdfjs.GlobalWorkerOptions.workerPort = new PdfWorker();
        return pdfjs;
    })().catch(error => { loading = null; throw error; });

    return loading;
}
