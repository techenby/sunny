{{-- Inline so it still runs when the page's bundle fails to load (e.g. stale assets after a deploy) --}}
<script data-navigate-once>
    window.kioskRecovery ??= {
        failures: 0,
        recovering: false,
        maxFailures: 3,
        retryAfter: 30_000,

        fail() {
            this.failures++

            if (this.failures >= this.maxFailures) {
                this.recover()
            }
        },

        succeed() {
            this.failures = 0
        },

        // Only reload once the page actually loads, so an outage doesn't
        // strand the kiosk on the browser's own error page.
        async recover() {
            if (this.recovering) {
                return
            }

            this.recovering = true

            while (true) {
                if (navigator.onLine) {
                    try {
                        let response = await fetch(window.location.href, { cache: 'no-store' })

                        if (response.ok) {
                            return this.reload()
                        }
                    } catch {}
                }

                await new Promise((resolve) => setTimeout(resolve, this.retryAfter))
            }
        },

        reload() {
            window.location.reload()
        },
    }

    setTimeout(() => window.Livewire || window.kioskRecovery.recover(), 10_000)

    window.addEventListener('online', () => window.kioskRecovery.recover())

    document.addEventListener('livewire:init', () => {
        Livewire.interceptRequest(({ onSuccess, onError, onFailure }) => {
            onSuccess(() => window.kioskRecovery.succeed())

            onFailure(() => window.kioskRecovery.fail())

            onError(({ response, preventDefault }) => {
                // Livewire's default is a confirm() or an error modal, which
                // would sit on an unattended kiosk until someone taps it.
                preventDefault()

                if ([401, 403, 419].includes(response.status)) {
                    window.kioskRecovery.recover()
                } else if (response.status >= 500) {
                    window.kioskRecovery.fail()
                }
            })
        })
    })
</script>
