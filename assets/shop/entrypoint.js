/**
 * This file only exists for tests purpose with Sylius Test App since it is declared in
 * vendor/sylius/test-application/webpack.config.js line 40 with
 * .addEntry('app-admin-entry', '../../../assets/admin/entrypoint.js')
 * This is the same for assets/shop/controllers.json declared in
 * vendor/sylius/test-application/webpack.config.js line 49 with
 *  .enableStimulusBridge(path.resolve(__dirname, '../../../assets/admin/controllers.json'))
 */

import { startStimulusApp } from '@symfony/stimulus-bridge';

startStimulusApp();

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
