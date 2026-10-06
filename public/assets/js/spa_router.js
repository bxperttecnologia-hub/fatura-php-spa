
alert('teste');
const SpaRouter = (function () {

    const mainEl = () => document.getElementById('app-main');

    function showLoadingBar() {
        const bar = document.getElementById('spa-loading-bar');
        if (!bar) return;
        bar.classList.remove('done');
        bar.classList.add('active');
    }

    function hideLoadingBar() {
        const bar = document.getElementById('spa-loading-bar');
        if (!bar) return;
        bar.classList.remove('active');
        bar.classList.add('done');
        setTimeout(() => {
            bar.classList.remove('done');
            bar.style.width = '0%';
        }, 400);
    }

    function reinitPageScripts() {
        if (window.lucide) lucide.createIcons();

        // Reexecuta qualquer <script> inline trazido dentro do
        // fragmento carregado via AJAX (scripts injetados via
        // innerHTML não rodam sozinhos)
        mainEl().querySelectorAll('script').forEach(oldScript => {
            const newScript = document.createElement('script');
            if (oldScript.src) {
                newScript.src = oldScript.src;
            } else {
                newScript.textContent = oldScript.textContent;
            }
            oldScript.replaceWith(newScript);
        });
    }

    function highlightActiveLink() {
        const currentPage = mainEl()?.dataset.page || '';

        document.querySelectorAll('#sidebar a[data-link]').forEach(link => {
            const linkPage = link.getAttribute('href').split('/').pop();
            link.classList.toggle('active', linkPage === currentPage);
        });
    }

    async function loadPage(url, pushState = true) {
        if (!url || url.startsWith('#')) return;

        showLoadingBar();

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) throw new Error('Falha ao carregar a página');

            const html = await response.text();

            mainEl().innerHTML = html;
            mainEl().dataset.page = url.split('/').pop().split('?')[0];

            if (pushState) {
                history.pushState({ url }, '', url);
            }

            reinitPageScripts();
            highlightActiveLink();
            window.__closeMobileMenu?.();

            // Sobe o scroll ao trocar de página
            mainEl().scrollTo?.(0, 0);
            window.scrollTo(0, 0);

        } catch (err) {
            console.error('SPA navigation error:', err);
            // Fallback: navegação normal se o AJAX falhar
            window.location.href = url;
        } finally {
            hideLoadingBar();
        }
    }

    function bindLinks() {
        document.body.addEventListener('click', function (e) {
            const link = e.target.closest('a[data-link]');
            if (!link) return;

            // Ignora links que abrem em nova aba ou com modificadores
            if (link.target === '_blank' || e.ctrlKey || e.metaKey) return;

            e.preventDefault();
            loadPage(link.getAttribute('href'));
        });

        window.addEventListener('popstate', function (e) {
            loadPage(location.pathname + location.search, false);
        });
    }

    bindLinks();

    return { loadPage, highlightActiveLink };
})();

window.SpaRouter = SpaRouter;