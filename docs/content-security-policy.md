# Content Security Policy (CSP)

If your Sylius application enforces a **Content Security Policy** via HTTP headers (e.g. with [NelmioSecurityBundle](https://github.com/nelmio/NelmioSecurityBundle)), you must whitelist the domains used by the HiPay JS SDK, PayPal, and the device-fingerprinting service.

> **If your application does not set any CSP header**, no action is required — everything works out of the box.

## Required directives

| CSP directive  | Domains to allow                                                        |
|----------------|-------------------------------------------------------------------------|
| `script-src`   | `*.hipay.com`, `*.paypal.com`, `mpsnare.iesnare.com`                    |
| `style-src`    | `*.hipay.com`                                                           |
| `img-src`      | `*.hipay.com`, `*.paypalobjects.com`                                    |
| `connect-src`  | `*.hipay.com`, `*.hipay-tpp.com`, `*.paypal.com`, `wss://mpsnare.iesnare.com` |
| `font-src`     | `*.gstatic.com`                                                         |
| `frame-src`    | `*.hipay.com`, `*.paypal.com`                                           |

Source: [HiPay JS SDK documentation](https://developer.hipay.com/online-payments/sdk-reference/sdk-js).

## NelmioSecurityBundle example

```yaml
# config/packages/nelmio_security.yaml
nelmio_security:
    csp:
        enabled: true
        hosts: []
        content_types: []
        enforce:
            script-src:
                - 'self'
                - '*.hipay.com'
                - '*.paypal.com'
                - 'mpsnare.iesnare.com'
            style-src:
                - 'self'
                - 'unsafe-inline'
                - '*.hipay.com'
            img-src:
                - 'self'
                - 'data:'
                - '*.hipay.com'
                - '*.paypalobjects.com'
            connect-src:
                - 'self'
                - '*.hipay.com'
                - '*.hipay-tpp.com'
                - '*.paypal.com'
                - 'wss://mpsnare.iesnare.com'
            font-src:
                - 'self'
                - '*.gstatic.com'
            frame-src:
                - 'self'
                - '*.hipay.com'
                - '*.paypal.com'
```

Adapt the `'self'`, `'unsafe-inline'`, and `'data:'` values to your own application needs.

## Why this is not auto-configured by the plugin

CSP headers are a **global, application-level concern**. The plugin cannot safely merge directives into an existing policy without risking conflicts or overrides. This is consistent with how other HiPay CMS plugins handle CSP: they document the requirements and leave enforcement to the integrator.
