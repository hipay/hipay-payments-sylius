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
const SDK_READY_TIMEOUT_MS = 30_000;

/**
 * Shared SDK instances keyed by the placeholder DOM element.
 *
 * LiveComponent re-renders cause Stimulus to instantiate fresh controllers on
 * the same placeholder; the first one mounts the SDK, subsequent ones skip
 * but still receive user events (Pay button click, paymentAuthorized callback).
 * This WeakMap lets a "skipper" controller find the SDK instance owned by its
 * sibling so that submitPayment() and the iframe-injected PayPal button keep
 * working regardless of which controller object Stimulus picked.
 *
 * The map is keyed by the placeholder element directly, so entries are
 * garbage-collected automatically when the placeholder leaves the DOM.
 *
 * @type {WeakMap<Element, object>}
 */
const SHARED_INSTANCES = new WeakMap();

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

      // Two-stage guard against concurrent mounts on the same selector
      // (LiveComponent re-renders can spawn a fresh controller before the
      // previous one has finished injecting iframes):
      //
      //   1. `data-hipay-mounted` is a SYNCHRONOUS flag set before we call
      //      `hipay.create()`. A racing sibling sees it immediately and skips,
      //      even though `placeholderTarget.children.length` is still 0
      //      (the SDK injects iframes asynchronously, after `create()` returns).
      //
      //   2. `children.length > 0` catches the case where the flag was lost
      //      (page hot-reload, navigation crash) but iframes are still in DOM.
      //
      // Skipping silently — instead of wiping the placeholder — keeps the
      // first controller's working SDK alive. The skipping controller leaves
      // `hostedPaymentsInstance` null, so its `disconnect()` is a no-op and
      // it never schedules a ready timeout that would surface a misleading
      // error to the user.
      if (this.placeholderTarget.dataset.hipayMounted === 'true'
          || this.placeholderTarget.children.length > 0) {
        // Pick up the active sibling's SDK instance so that this controller
        // can still service Pay-button clicks and PayPal iframe callbacks
        // routed to it by Stimulus (the click target's closest ancestor with
        // [data-controller] may be this skipper, not the active mount).
        const sharedInstance = SHARED_INSTANCES.get(this.placeholderTarget);
        if (sharedInstance) {
          this.hostedPaymentsInstance = sharedInstance;
        }
        this.loaderStop();
        return;
      }

      this.placeholderTarget.dataset.hipayMounted = 'true';
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

      // Publish the instance so skipper siblings can use it for submitPayment.
      if (this.hasPlaceholderTarget) {
        SHARED_INSTANCES.set(this.placeholderTarget, this.hostedPaymentsInstance);
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

    // Only tear down the SDK and clear the placeholder if WE created the
    // instance. A controller that skipped the mount in connect() (because
    // a sibling already owns the placeholder) must leave the DOM untouched —
    // otherwise we would wipe iframes belonging to the still-alive sibling
    // and the user would lose the working form.
    // A skipper controller never owned the instance — it just borrowed the
    // shared reference. Clear its local copy but do NOT touch the DOM nor
    // the SHARED_INSTANCES entry (still in use by the active sibling).
    if (this.hostedPaymentsInstance
        && this.hasPlaceholderTarget
        && SHARED_INSTANCES.get(this.placeholderTarget) === this.hostedPaymentsInstance
        && this.placeholderTarget.dataset.hipayMounted !== 'true') {
      this.hostedPaymentsInstance = undefined;
      return;
    }

    if (this.hostedPaymentsInstance) {
      if (typeof this.hostedPaymentsInstance.destroy === 'function') {
        try {
          this.hostedPaymentsInstance.destroy();
        } catch (_e) {
          // SDK destroy may throw when the iframe is already gone — ignore,
          // we just want the placeholder to end up empty.
        }
      }
      this.hostedPaymentsInstance = undefined;

      if (this.hasPlaceholderTarget) {
        this.placeholderTarget.innerHTML = '';
        delete this.placeholderTarget.dataset.hipayMounted;
        SHARED_INSTANCES.delete(this.placeholderTarget);
      }
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

  /**
   * Resolve the active SDK instance for this controller.
   *
   * `this.hostedPaymentsInstance` is the local reference set by `connect()`.
   * It can be null on a "skipper" controller whose connect ran during the
   * tiny window where the active sibling had set `data-hipay-mounted="true"`
   * but had not yet published its instance to {@link SHARED_INSTANCES}, or
   * on any controller that received a Stimulus action before its connect
   * finished. Falling back to the shared map ensures the click is always
   * served by the live SDK, regardless of which controller object Stimulus
   * routed it to.
   *
   * @returns {object|null}
   */
  resolveHostedPaymentsInstance() {
    if (this.hostedPaymentsInstance) {
      return this.hostedPaymentsInstance;
    }
    if (this.hasPlaceholderTarget) {
      const shared = SHARED_INSTANCES.get(this.placeholderTarget);
      if (shared) {
        this.hostedPaymentsInstance = shared;
        return shared;
      }
    }

    return null;
  }

  async submitPayment() {
    const instance = this.resolveHostedPaymentsInstance();
    if (!instance) {
      // No active SDK on this placeholder — bail silently. Either the user
      // clicked before mount finished (rare; the loader is still spinning),
      // or the click was routed to a stale controller that lost its WeakMap
      // entry. Doing nothing is safer than surfacing a misleading error.
      this.loaderStop();
      return;
    }

    try {
      this.loaderStart();
      const paymentData = await instance.getPaymentData();
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
