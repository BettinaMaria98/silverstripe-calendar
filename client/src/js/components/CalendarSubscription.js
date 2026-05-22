export class CalendarSubscription {
    constructor()
    {
        this.init();
    }

    init()
    {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.bindEvents());
        } else {
            this.bindEvents();
        }
    }

    bindEvents()
    {
        // Move modal to body to avoid position:fixed issues inside transformed containers
        const modal = document.getElementById('subscribeModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        // Modal shown event to populate subscription URL
        if (modal) {
            modal.addEventListener('shown.bs.modal', () => this.updateSubscribeButton());
        }

        // Subscribe in App
        document.addEventListener('click', (e) => {
            if (e.target.closest('.js-subscribe-app')) {
                e.preventDefault();
                this.subscribeInApp(e);
            }
        });

        // Copy URL buttons
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-copy-url, .js-copy-subscription-url');
            if (btn) {
                e.preventDefault();
                this.copyUrl(btn);
            }
        });
    }

    updateSubscribeButton()
    {
        const urlInput = document.querySelector('#subscription-url');
        const subscribeButton = document.querySelector('.js-subscribe-app');

        if (!urlInput) return;

        if (!urlInput.value.trim()) {
            const calendarButton = document.querySelector('.js-subscribe-calendar');
            if (calendarButton) {
                const calendarUrl = calendarButton.getAttribute('data-calendar-url');
                if (calendarUrl) {
                    const base = /^https?:\/\//i.test(calendarUrl)
                        ? calendarUrl
                        : `${window.location.origin}${calendarUrl}`;
                    urlInput.value = `${base.replace(/\/$/, '')}/ical`;
                }
            }
        }

        if (subscribeButton) {
            const webcalUrl = urlInput.value.replace(/^https?:\/\//, 'webcal://');
            subscribeButton.setAttribute('href', webcalUrl);
            subscribeButton.setAttribute('data-webcal-url', webcalUrl);
        }
    }

    copyUrl(button)
    {
        const input = document.querySelector('#subscription-url');
        if (!input) return;

        const originalHtml = button.innerHTML;
        const showFeedback = () => {
            button.innerHTML = '<i class="bi bi-check"></i> Kopiert!';
            setTimeout(() => { button.innerHTML = originalHtml; }, 2000);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value)
                .then(showFeedback)
                .catch(() => this.copyFallback(input, showFeedback));
        } else {
            this.copyFallback(input, showFeedback);
        }
    }

    copyFallback(input, callback)
    {
        input.select();
        input.setSelectionRange(0, 99999);
        try {
            document.execCommand('copy');
            callback();
        } catch {
            console.warn('Copy not supported');
        }
    }

    subscribeInApp()
    {
        const urlInput = document.querySelector('#subscription-url');
        if (!urlInput) return;
        window.location.href = urlInput.value.replace(/^https?:\/\//, 'webcal://');
    }
}

export default CalendarSubscription;