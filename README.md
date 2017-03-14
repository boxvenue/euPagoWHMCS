# euPagoWHMCS
Gateway Module for WHMCS that integrates eupago.pt payment system. This module was created the the original one provided contained vuln. and messy code. Any Contributes are welcome
Modulo euPago para WHMCS, o modulo foi criado como melhoria ao original.

This is a beta version so might still have some issues.
The module can be improved still without touching the core features of WHMCS

For more information, please refer to the documentation at:
http://docs.whmcs.com/Gateway_Module_Developer_Docs

## Recommended Module Content ##

The recommended structure of a third party gateway module is as follows.

```
 modules/gateways/
  |- callback/eupagocallback.php
  |  eupagombway.php
  |  eupagomultibanco.php
  |  eupagopagaqui.php
  |  eupagopaysafecard.php
  |  eupagopayshop.php
```

## Minimum Requirements ##

For the latest WHMCS minimum system requirements, please refer to
http://docs.whmcs.com/System_Requirements

We recommend your module follows the same minimum requirements wherever
possible.

## Useful Resources
* [Developer Resources](http://www.whmcs.com/developers/)
* [Hook Documentation](http://docs.whmcs.com/Hooks)
* [API Documentation](http://docs.whmcs.com/API)

[WHMCS Limited](http://www.whmcs.com)

# TODO List
- [x] Improve Callback
- [x] Update EuPago - Payshop
- [x] Update EuPago - PaySafeCard
- [x] Update EuPago - PagoAqui
- [ ] Check for Remaining Translations
- [ ] Do English Translations
- [ ] Rename tables to mod_eupago_*
- [ ] Implement Traits for Soap Client and Template rendering
- [ ] Join all subclasses in one main class to server multiple mini-gateways
- [ ] Add Credit Card Processor
- [ ] Add options to set Amount Max/min per Payment
- [ ] Add option to set a due date for a certain Payment
- [ ] Add Credit Card Pre-Charge
- [ ] Add Get Payment system
- [ ] Move Payment Statuses to static.

# Faking Success Payments
Visit : https://www.hurl.it/
Destination : GET http://xxxx.com/modules/gateways/callback/eupagocallback.php
Params : 
 - mp = PC:PT ( for Multibanco )
 - chave_api = xxxx-xxxx-xxxx ( Your private key keep it safe )
 - referencia = 1234567 ( Your reference number ) 
 - valor = 19.5 ( Your amount in cash )
 
 
