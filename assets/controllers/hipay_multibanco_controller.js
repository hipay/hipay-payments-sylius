import { Controller } from '@hotwired/stimulus';

import { createHiPayFromSdkConfig } from './hipay_hosted_fields_controller.js';

export default class extends Controller {
  static values = {
    initialConfig: { type: Object, default: {} },
    reference: String,
    entity: String,
    amount: String,
    currency: String,
    expiry: String,
  };

  connect() {
    const eligibility = this.initialConfigValue?.eligibility;
    if (eligibility?.blocked === true) {
      return;
    }

    const hipay = createHiPayFromSdkConfig(this.initialConfigValue);
    if (!hipay || typeof hipay.createReference !== 'function') {
      console.warn('[hipay-multibanco] HiPay client or createReference is not available.');
      return;
    }

    const selector = this.element.id;
    if (!selector) {
      console.warn('[hipay-multibanco] The host element must have an id used as HiPay selector.');
      return;
    }

    hipay.createReference('multibanco', {
      selector,
      reference: this.referenceValue,
      entity: this.entityValue,
      amount: this.amountValue,
      currency: this.currencyValue,
      expirationDate: this.expiryValue,
    });
  }
}
