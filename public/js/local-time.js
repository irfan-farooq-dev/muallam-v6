/*
 * Shows dates in the reader's own time zone. The server stores and prints times in UTC inside
 * <time datetime="..." data-local-time="format"> (see components/local-time.blade.php); this rewrites them.
 * data-local-title: keep the text (e.g. "5 minutes ago") and put the local date and time in the hover title instead.
 * data-local-title-prefix: text placed before the date in that title (e.g. "Goes live ").
 */
(function () {
    const formats = {
        datetime: { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true },
        date: { day: 'numeric', month: 'short', year: 'numeric' },
        'long-date': { day: 'numeric', month: 'long', year: 'numeric' },
        month: { month: 'long', year: 'numeric' },
        'weekday-date': { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
    };

    function format(date, name) {
        return new Intl.DateTimeFormat('en-GB', formats[name] || formats.datetime).format(date).replace(/\b(AM|PM)\b/g, (m) => m.toLowerCase());
    }

    function localizeTimes(root) {
        (root || document).querySelectorAll('[data-local-time]').forEach((element) => {
            const date = new Date(element.getAttribute('datetime'));
            if (Number.isNaN(date.getTime())) {
                return;
            }

            const text = format(date, element.dataset.localTime);
            if (element.hasAttribute('data-local-title')) {
                element.title = (element.dataset.localTitlePrefix || '') + text;
            } else {
                element.textContent = text;
            }
        });
    }

    window.localizeTimes = localizeTimes;
    document.addEventListener('DOMContentLoaded', () => localizeTimes(document));
})();
