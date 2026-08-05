/**
 * Shop front-end bundle. Register Stimulus controllers with `enableStimulusBridge` targeting
 * `vendor/…/hipay-payments-sylius/assets/shop/controllers.json` (hosted fields, Multibanco, …).
 */

import { startStimulusApp } from '@symfony/stimulus-bridge';
import './hipay-awaiting-payment.css';

/*
 * HiPay Hosted Fields Controller event listeners — demo / debug.
 *
 * These listeners showcase every public CustomEvent emitted by the
 * hipay_hosted_fields_controller Stimulus controller. They are here so the
 * events can be observed in the browser console of the test application.
 *
 * In a real application, the host app would register similar listeners
 * in its own entrypoint to customize the HiPay plugin behavior without
 * overriding the Stimulus controller itself.
 */

document.addEventListener('hipay:hosted-fields:before-connect', (event) => {
  console.group('[HiPay Event] hipay:hosted-fields:before-connect');
  console.log('SDK config (mutable via event.detail.config):', event.detail.config);
  console.log('Controller:', event.detail.controller);
  console.log('To cancel SDK init, call event.preventDefault()');
  console.groupEnd();
});

document.addEventListener('hipay:hosted-fields:ready', (event) => {
  console.group('[HiPay Event] hipay:hosted-fields:ready');
  console.log('Hosted fields instance:', event.detail.instance);
  console.groupEnd();
});

document.addEventListener('hipay:hosted-fields:before-submit', (event) => {
  console.group('[HiPay Event] hipay:hosted-fields:before-submit');
  console.log('Payment data (mutable via event.detail.paymentData):', event.detail.paymentData);
  console.log('To block submission, call event.preventDefault()');
  console.groupEnd();
});

document.addEventListener('hipay:hosted-fields:after-submit', (event) => {
  console.group('[HiPay Event] hipay:hosted-fields:after-submit');
  console.log('Submitted payment data:', event.detail.paymentData);
  console.groupEnd();
});

document.addEventListener('hipay:hosted-fields:error', (event) => {
  console.group('[HiPay Event] hipay:hosted-fields:error');
  console.error('Errors:', event.detail.errors);
  console.groupEnd();
});
