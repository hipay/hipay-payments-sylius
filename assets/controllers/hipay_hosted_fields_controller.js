import { Controller } from '@hotwired/stimulus';

/**
 * HiPay client from the same bootstrap payload as PHP getJsSdkConfig() (checkout / thank-you).
 *
 * @param {Record<string, *>|undefined} initialConfig
 * @returns {ReturnType<typeof HiPay>|null}
 */
export function createHiPayFromSdkConfig(initialConfig) {
  if (typeof HiPay === 'undefined') {
    return null;
  }
  const cfg = initialConfig ?? {};
  const { username, password, environment, lang } = cfg;
  if (username == null || password == null) {
    return null;
  }

  return HiPay({
    username,
    password,
    environment: environment ?? 'stage',
    lang: lang ?? 'en',
  });
}

/** Failsafe when the SDK never emits `ready` (e.g. payment product disabled on HiPay BO). */
const SDK_READY_TIMEOUT_MS = 3_000;

export default class extends Controller {
  hostedPaymentsInstance = null;
  logger = console;

  /** @type {number|undefined} */
  _readyTimeoutId;

  /** @type {() => void|undefined} */
  _onPaymentProcessedBound;

  static targets = [ 'error', 'loader', 'button', 'placeholder' ];
  static values = {
    initialConfig: { type: Object, default: {} },
  };

  /**
   * Dispatch a namespaced CustomEvent on the controller element.
   *
   * @param {string} name - Event name suffix (e.g. 'before-connect')
   * @param {Object} detail - Mutable detail object
   * @param {boolean} cancelable - Whether preventDefault() is supported
   * @returns {CustomEvent}
   */
  emitEvent(name, detail = {}, cancelable = false) {
    const event = new CustomEvent(`hipay:hosted-fields:${name}`, {
      bubbles: true,
      cancelable,
      detail: {
        controller: this,
        element: this.element,
        ...detail,
      },
    });

    this.element.dispatchEvent(event);

    return event;
  }

  connect() {
    const eligibility = this.initialConfigValue.eligibility;
    if (eligibility && eligibility.blocked === true) {
      if (eligibility.messages.length > 0) {
        this.loaderStop();
        this.displayError(eligibility.messages);
      }
      return;
    }

    const browserCheck = this.initialConfigValue.browserCheck;
    if (browserCheck && !this.checkBrowserSupport(browserCheck.type)) {
      this.loaderStop();
      this.displayError([browserCheck.message]);
      return;
    }

    let { product, username, password, environment, debug, lang, configuration } = this.initialConfigValue;

    configuration = {
      ...configuration,
      brand: configuration.brand ? Object.values(configuration.brand) : undefined,
    };

    if (this.hasPlaceholderTarget) {
      configuration.selector = this.placeholderTarget.id;
    }

    const sdkConfig = { product, username, password, environment, lang, configuration };

    const beforeConnect = this.emitEvent('before-connect', { config: sdkConfig }, true);

    if (beforeConnect.defaultPrevented) {
      this.loaderStop();
      return;
    }

    const finalConfig = beforeConnect.detail.config;

    try {
      const hipay = createHiPayFromSdkConfig(finalConfig);
      if (!hipay) {
        this.loaderStop();
        return;
      }
      this.hostedPaymentsInstance = hipay.create(finalConfig.product, finalConfig.configuration);

      if (!this.hostedPaymentsInstance) {
        this.loaderStop();
        return;
      }

      this.clearReadyTimeout();
      this.scheduleReadyTimeout();

      this.hostedPaymentsInstance.on('ready', () => {
        this.clearReadyTimeout();
        this.loaderStop();
        this.emitEvent('ready', { instance: this.hostedPaymentsInstance });
      });

      this.hostedPaymentsInstance.on('change', (response) => {
        if (this.hasButtonTarget) {
          this.buttonTarget.disabled = !response.valid;
        }
      });

      this.hostedPaymentsInstance.on('paymentAuthorized', (paymentData) => {
        this.processPaymentData(paymentData);
      });

      this.hostedPaymentsInstance.on('paymentUnauthorized', (error) => {
        this.clearReadyTimeout();
        this.completePaymentWithFailure();
        this.displayError(this.normalizeErrorMessages(error));
        this.loaderStop();
        if (true === debug) {
          this.logFormattedMessage('[HiPay Hosted Fields Controller]', 'paymentUnauthorized', {
            'Error': error
          })
        }
      });

      this.hostedPaymentsInstance.on('cancel', () => {
        this.clearReadyTimeout();
        this.completePaymentWithFailure();
        this.loaderStop();
        this.emitEvent('cancel', {});
      });

      if (finalConfig.product === 'hosted-payments' && typeof this.hostedPaymentsInstance.on === 'function') {
        this.hostedPaymentsInstance.on('validityChange', (response) => {
          if (response && response.valid === false && response.error) {
            this.clearReadyTimeout();
            this.loaderStop();
            this.displayError(this.normalizeErrorMessages([response.error]));
          }
        });
      }

      this._onPaymentProcessedBound = () => this.submitForm();
      window.addEventListener('hipay:payment:processed', this._onPaymentProcessedBound);

      if (true === debug) {
        this.logFormattedMessage('[HiPay Hosted Fields Controller]', 'connect', {
          'Product': finalConfig.product,
          'Username': finalConfig.username,
          'Password': finalConfig.password,
          'Environment': finalConfig.environment,
          'Lang': finalConfig.lang,
          'Configuration': finalConfig.configuration,
          'Hipay object': hipay,
          'Card instance': this.hostedPaymentsInstance,
        })
      }
    } catch (e) {
      this.clearReadyTimeout();
      this.loaderStop();
      this.displayError(this.normalizeErrorMessages(e));
      this.emitEvent('error', { errors: e });
    }
  }

  disconnect() {
    this.clearReadyTimeout();
    if (this._onPaymentProcessedBound) {
      window.removeEventListener('hipay:payment:processed', this._onPaymentProcessedBound);
      this._onPaymentProcessedBound = undefined;
    }
  }

  clearReadyTimeout() {
    if (this._readyTimeoutId != null) {
      clearTimeout(this._readyTimeoutId);
      this._readyTimeoutId = undefined;
    }
  }

  scheduleReadyTimeout() {
    console.log('scheduleReadyTimeout');
    this._readyTimeoutId = window.setTimeout(() => {
      this._readyTimeoutId = undefined;
      this.loaderStop();
      this.displayError([this.sdkLoadFailedMessage()]);
      this.emitEvent('error', { errors: new Error('HiPay SDK ready timeout') });
    }, SDK_READY_TIMEOUT_MS);
  }

  /**
   * Translated message when the hosted fields iframe never becomes ready.
   * @returns {string}
   */
  sdkLoadFailedMessage() {
    return this.initialConfigValue.clientMessages?.sdkLoadFailed
      ?? this.initialConfigValue.clientMessages?.paymentProcessingFailed
      ?? 'This payment method could not be loaded. Please try another one or contact the store.';
  }

  /**
   * @param {unknown} item
   * @returns {string}
   */
  stringifyErrorItem(item) {
    if (item == null) {
      return '';
    }
    if (typeof item === 'string') {
      return item;
    }
    if (typeof item === 'number' || typeof item === 'boolean') {
      return String(item);
    }
    if (item instanceof Error) {
      return item.message;
    }
    if (typeof item === 'object' && item !== null) {
      if (typeof item.message === 'string') {
        return item.message;
      }
      if (typeof item.error === 'string') {
        return item.error;
      }
    }
    try {
      return JSON.stringify(item);
    } catch (err) {
      return String(item);
    }
  }

  /**
   * @param {unknown} input
   * @returns {string[]}
   */
  normalizeErrorMessages(input) {
    const fallback = this.initialConfigValue.clientMessages?.paymentProcessingFailed
      ?? this.sdkLoadFailedMessage();

    if (input == null) {
      return [fallback];
    }
    if (Array.isArray(input)) {
      const mapped = input.map((item) => this.stringifyErrorItem(item)).filter((s) => s !== '');
      return mapped.length > 0 ? mapped : [fallback];
    }
    const s = this.stringifyErrorItem(input);
    return s ? [s] : [fallback];
  }

  submitForm() {
    const { debug } = this.initialConfigValue;

    if (true === debug) {
      this.logFormattedMessage('[HiPay Hosted Fields Controller]', 'submitForm', )
    }

    this.completePaymentWithSuccess();

    const form = this.element.closest('form');
    form.submit();
  }

  completePaymentWithSuccess() {
    if (typeof this.hostedPaymentsInstance?.completePaymentWithSuccess === 'function') {
      this.hostedPaymentsInstance.completePaymentWithSuccess();
    }
  }

  completePaymentWithFailure() {
    if (typeof this.hostedPaymentsInstance?.completePaymentWithFailure === 'function') {
      this.hostedPaymentsInstance.completePaymentWithFailure();
    }
  }

  /**
   * @param {string} type - Browser capability check identifier
   * @returns {boolean}
   */
  checkBrowserSupport(type) {
    if (type === 'applePaySession') {
      try {
        return window.ApplePaySession !== undefined
          && window.ApplePaySession.canMakePayments();
      } catch (e) {
        return false;
      }
    }

    return true;
  }

  displayError(messages) {
    if (!this.hasErrorTarget) return;

    if (messages.length === 0) {
      this.removeError();
      return;
    }

    this.errorTarget.classList.remove('d-none');
    this.errorTarget.replaceChildren();

    if (messages.length === 1) {
      this.errorTarget.appendChild(document.createTextNode(messages[0]));
      return;
    }

    const list = document.createElement('ul');
    list.className = 'mb-0 ps-3';
    messages.forEach((text) => {
      const item = document.createElement('li');
      item.appendChild(document.createTextNode(text));
      list.appendChild(item);
    });
    this.errorTarget.appendChild(list);
  }

  removeError() {
    if (!this.hasErrorTarget) return;

    this.errorTarget.classList.add('d-none');
    this.errorTarget.innerHTML = '';
  }

  loaderStart() {
    if (!this.hasLoaderTarget) return;

    this.loaderTarget.classList.remove('d-none');
  }

  loaderStop() {
    if (!this.hasLoaderTarget) return;

    this.loaderTarget.classList.add('d-none');
  }

  async initialize() {
    const { debug } = this.initialConfigValue;

    await this.getComponent(this.element);

    if (true === debug) {
      this.logFormattedMessage('[HiPay Hosted Fields Controller]', 'initialize', {
        'Component': this.component
      })
    }
  }

  async submitPayment() {
    try {
      this.loaderStart();
      const paymentData = await this.hostedPaymentsInstance.getPaymentData();
      await this.processPaymentData(paymentData);
    } catch (errors) {
      this.completePaymentWithFailure();
      this.loaderStop();
      this.displayError(this.normalizeErrorMessages(errors));
      this.emitEvent('error', { errors });

      if (true === this.initialConfigValue.debug) {
        this.logFormattedMessage('[HiPay Hosted Fields Controller]', 'submitPayment', {
          'Errors': errors
        })
      }
    }
  }

  /**
   * Shared pipeline for all payment products (card, PayPal, etc.).
   * Emits before-submit / after-submit events, sends data to the LiveComponent.
   *
   * @param {Object} paymentData - Data from getPaymentData() or paymentAuthorized
   */
  async processPaymentData(paymentData) {
    const { debug } = this.initialConfigValue;

    try {
      this.loaderStart();

      const beforeSubmit = this.emitEvent('before-submit', { paymentData }, true);

      if (beforeSubmit.defaultPrevented) {
        this.loaderStop();
        return;
      }

      const finalPaymentData = beforeSubmit.detail.paymentData;

      this.component.action('processPayment', {response: JSON.stringify(finalPaymentData)});

      this.emitEvent('after-submit', { paymentData: finalPaymentData });

      if (true === debug) {
        this.logFormattedMessage('[HiPay Hosted Fields Controller]', 'processPaymentData', {
          'Response': finalPaymentData
        })
      }
    } catch (errors) {
      this.completePaymentWithFailure();
      this.loaderStop();
      this.displayError(this.normalizeErrorMessages(errors));
      this.emitEvent('error', { errors });

      if (true === debug) {
        this.logFormattedMessage('[HiPay Hosted Fields Controller]', 'processPaymentData', {
          'Errors': errors
        })
      }
    }
  }

  logFormattedMessage(identifier, functionName, detail = {}) {
    detail = Object.assign({ application: this }, detail);
    this.logger.groupCollapsed(`${identifier} #${functionName}()`);
    Object.entries(detail).forEach(([key, value]) => {
      console.log(key, value);
    });
    this.logger.groupEnd();
  }

  getComponent(element) {
    return new Promise((resolve, reject) => {
      if (element.__component) {
        this.component = element.__component;
        resolve();
        return;
      }

      const maxAttempts = 50;
      let attempts = 0;
      const interval = setInterval(() => {
        attempts++;
        if (element.__component) {
          clearInterval(interval);
          this.component = element.__component;
          resolve();
        } else if (attempts >= maxAttempts) {
          clearInterval(interval);
          reject(new Error('[hipay-hosted-fields] Live component not found after ' + maxAttempts + ' attempts'));
        }
      }, 50);
    });
  }
}
