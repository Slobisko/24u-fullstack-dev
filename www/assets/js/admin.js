document.addEventListener('DOMContentLoaded', function () {
    initFlashMessages();
    initCoverPreview();
    initBookSearch();
});

function initFlashMessages() {
    document.querySelectorAll('.flash-message').forEach(function (flash) {
        setTimeout(function () {
            flash.remove();
        }, 5000);
    });
}

function initCoverPreview() {
    var coverInput = document.querySelector('.js__cover-input');

    if (!coverInput) {
        return;
    }

    var preview = document.querySelector('.js__cover-preview');
    var placeholder = document.querySelector('.js__cover-placeholder');
    var fileName = document.querySelector('.js__cover-name');

    coverInput.addEventListener('change', function () {
        var file = coverInput.files[0];

        if (!file) {
            return;
        }

        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
        placeholder.hidden = true;
        fileName.textContent = 'Vybráno: ' + file.name;
    });
}

function initBookSearch() {
    var form = document.querySelector('.js__admin-search');

    if (!form) {
        return;
    }

    var input = form.querySelector('input[name="q"]');
    var clearLink = form.querySelector('.js__search-clear');
    var submitButton = form.querySelector('.js__search-submit');
    var debounceTimer = null;
    var controller = null;

    // Results update while typing, so the submit button is only needed without JavaScript.
    submitButton.hidden = true;

    // Loads the book list for the given URL and swaps in the snippets returned by the server.
    function loadBooks(url) {
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then(function (payload) {
                Object.keys(payload.snippets || {}).forEach(function (id) {
                    var snippet = document.getElementById(id);
                    if (snippet) {
                        snippet.innerHTML = payload.snippets[id];
                    }
                });

                window.history.replaceState(null, '', url);
                clearLink.hidden = !url.searchParams.get('q');
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    window.location.href = url;
                }
            });
    }

    // Keeps the current sorting and only changes the search query.
    function search() {
        var query = input.value.trim();
        var url = new URL(window.location.href);
        url.searchParams.delete('_fid');

        if (query !== '') {
            url.searchParams.set('q', query);
        } else {
            url.searchParams.delete('q');
        }

        loadBooks(url);
    }

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(search, 250);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearTimeout(debounceTimer);
        search();
    });

    clearLink.addEventListener('click', function (event) {
        event.preventDefault();
        input.value = '';
        clearTimeout(debounceTimer);
        search();
        input.focus();
    });

    // Sort links are re-rendered with the table, so listen on the document.
    document.addEventListener('click', function (event) {
        var sortLink = event.target.closest('.js__sort-link');

        if (!sortLink) {
            return;
        }

        event.preventDefault();
        clearTimeout(debounceTimer);
        loadBooks(new URL(sortLink.href));
    });
}
