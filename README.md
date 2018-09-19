# saas-modules

These is a database schema, using Laravel (PHP) migrations, for a SaaS Freemium platform to enable Users to buy modules to enhance the functionality.

The onus is on revenue generation for the Saas platform.

The User is likely not the financial decision maker (FDM). The thinking behind the design is that the User wants the module, the Saas owner wants the User to have the module (for revenue), the FDM decision (after much nudging) often defaults to No.

Therefore when a User adds a module, the FDM receives a notification giving them 14 days to cancel. The module functionality is fully enabled during this period. If the FDM doesn't cancel, the module is contracted monthly or annually, depending on how it was purchased. If the module is cancelled, another implict 'trial' isn't possible.

Module Billing is accumulated monthly and not per module. IE, 100 modules would create an enormous number of bank transactions per day for the SaaS platform and the User. Annually billing looks expensive. Small monthly amounts go unnoticed - IE too small to be worth be considering.

Therefore after the 14 day cooling off period the usage is pro rata'd along with the next month into 1 transaction for the SaaS next billing date - for clean clear billing.

A 3rd party module, eg Stripe Payments likely required the user to accept additional Terms and Conditions. Each module has the option for many T&Cs.

The price plans are versioned. When on a grandfather plan the User would get the current module price, and not the module price when they signed up.

One module can be in many categories.
