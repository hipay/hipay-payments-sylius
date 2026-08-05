import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['loader', 'statusMessage', 'expiredMessage'];
    static values = {
        statusUrl: String,
        successUrl: String,
        failureUrl: String,
        pollInterval: Number,
        expiration: Number,
    };

    connect() {
        this._startedAt = Date.now();
        this._timer = setTimeout(() => this._poll(), this.pollIntervalValue * 1000);
    }

    disconnect() {
        clearTimeout(this._timer);
    }

    _poll() {
        if (Date.now() - this._startedAt >= this.expirationValue * 1000) {
            this.loaderTarget.classList.add('d-none');
            this.statusMessageTarget.classList.add('d-none');
            this.expiredMessageTarget.classList.remove('d-none');
            return;
        }

        fetch(this.statusUrlValue)
            .then((r) => r.json())
            .then((data) => {
                if (data.is_success) {
                    window.location.href = this.successUrlValue;
                } else if (data.is_failure) {
                    window.location.href = this.failureUrlValue;
                } else {
                    this._timer = setTimeout(() => this._poll(), this.pollIntervalValue * 1000);
                }
            })
            .catch(() => {
                this._timer = setTimeout(() => this._poll(), this.pollIntervalValue * 1000);
            });
    }
}
