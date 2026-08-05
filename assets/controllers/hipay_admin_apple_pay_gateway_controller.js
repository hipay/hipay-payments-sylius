import { Controller } from '@hotwired/stimulus';

/**
 * Admin HiPay gateway config — toggles Merchant identifier visibility and required label styling
 * when "Display the button only if a card is active" changes.
 *
 * Attached to the Apple Pay configuration card-body (gateway configuration template).
 */
export default class extends Controller {
    connect() {
        if (
            !this.element.querySelector('[data-hipay-apple-pay-merchant-row]')
            || !this.element.querySelector('[name*="[only_active_card]"]')
        ) {
            return;
        }

        this.boundHandleChange = (event) => {
            this.handleChange(event);
        };
        this.element.addEventListener('change', this.boundHandleChange);
        this.syncMerchantField();
    }

    disconnect() {
        if (this.boundHandleChange) {
            this.element.removeEventListener('change', this.boundHandleChange);
        }
    }

    handleChange(event) {
        const { target } = event;
        if (!(target instanceof HTMLSelectElement || target instanceof HTMLInputElement)) {
            return;
        }

        if (!target.name || !target.name.includes('[only_active_card]')) {
            return;
        }

        this.syncMerchantField();
    }

    syncMerchantField() {
        const merchantRow = this.element.querySelector('[data-hipay-apple-pay-merchant-row]');
        if (!merchantRow) {
            return;
        }

        let merchantRequired = false;
        const select = this.element.querySelector('select[name*="[only_active_card]"]');
        if (select && select.selectedIndex >= 0) {
            merchantRequired = this.requiresMerchant(select.options[select.selectedIndex]);
        } else {
            const checked = this.element.querySelector('input[name*="[only_active_card]"]:checked');
            merchantRequired = this.requiresMerchant(checked);
        }

        merchantRow.classList.toggle('d-none', !merchantRequired);
        this.syncMerchantIdentifierLabel(merchantRequired, merchantRow);
    }

    requiresMerchant(optionOrInput) {
        if (!optionOrInput) {
            return false;
        }

        // Primary: explicit data attribute set via choice_attr on the <option>
        if (optionOrInput.hasAttribute('data-requires-merchant-identifier')) {
            return true;
        }

        // Fallback: PHP true serialises to "1" as a select value
        return optionOrInput.value === '1';
    }

    syncMerchantIdentifierLabel(required, merchantRow) {
        const widget = merchantRow.querySelector('[name*="[merchant_identifier]"]');
        if (!(widget instanceof HTMLElement) || !widget.id) {
            return;
        }

        const label = merchantRow.querySelector(`label[for="${CSS.escape(widget.id)}"]`);

        if (!(label instanceof HTMLElement)) {
            return;
        }

        label.classList.toggle('required', required);
    }

}
