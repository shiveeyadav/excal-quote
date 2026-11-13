# Excal_Quote - Official quotations for excaliburlabware.com

Magento 2.4.8 Open Source module. It works like LabFriend's "Instant Quote": a university, NHS or public lab
turns its basket into an official supplier quotation and uses that quotation to raise a purchase order.

- Basket page: **Get an official quotation** button. A mini-cart link is optional (off by default).
- `/quote/request`: form with organisation, department, contact name, email, phone, billing address, delivery
  address, optional customer reference/PO, and notes. Logged-in customers get it prefilled. The form has a form key,
  a hidden honeypot, server-side validation and a per-session hourly limit.
- On submit the module saves `excal_quote` and `excal_quote_item`:
  - quote number `EXQ-YYYY-000123`, a random 48-character public hash, and the lines (SKU, name, qty, unit price
    ex VAT, line total)
  - delivery from the **Flat Rate** carrier config, VAT (20% by default) on goods + delivery, and the total in GBP
  - valid-until date (30 days by default), status `open/converted/expired`, created date
- `/quote/view/id/<hash>`: public printable A4 page (custom router), served with `noindex` (meta tag and
  `X-Robots-Tag`). **Download PDF / Print** opens the browser print dialog (`window.print`). Supplier details come
  from admin config. Any empty field prints as a red **[NOT SET: ...]** marker. The module never makes up company
  data.
- Emails use Magento email templates: the customer gets the link, the owner gets a copy (reply-to = customer). A
  failed email is logged to `var/log/system.log` and the quotation is still saved.
- Admin: **Sales > Operations > Official Quotations** grid with filters, a "View / print" link and a mass
  "Change status" action.
- Cron `excal_quote_expire` runs daily at 02:15 server time and sets open quotations past their valid-until date
  to `expired`.
- **Disabled by default** (`excal_quote/general/enabled = 0`).

## Files

```
app/code/Excal/Quote/
  registration.php, composer.json
  etc/module.xml, config.xml, db_schema.xml, db_schema_whitelist.json, di.xml, acl.xml,
      crontab.xml, email_templates.xml
  etc/frontend/routes.xml (frontName "quote"), etc/frontend/di.xml (custom router)
  etc/adminhtml/routes.xml (frontName "excalquote"), menu.xml, system.xml
  Controller/Router.php                      /quote/view/id/<hash> -> quote/view/index
  Controller/Request/Index.php, Save.php     form + POST
  Controller/View/Index.php                  printable page
  Controller/Adminhtml/Quote/Index.php, MassStatus.php
  Cron/ExpireQuotes.php
  Model/Config.php, Quote.php, QuoteItem.php, QuoteCreator.php, QuoteLoader.php, Mailer.php,
        FormValidator.php, UrlProvider.php, Source/Status.php, ResourceModel/...
  Ui/Component/Listing/Column/Actions.php
  ViewModel/Button.php, RequestForm.php, QuoteView.php
  view/frontend/layout/*.xml, templates/{cart,request,view}/*.phtml, web/css/quote.css,
       web/js/print.js, email/quote_customer.html, email/quote_owner.html
  view/adminhtml/layout/excalquote_quote_index.xml, ui_component/excal_quote_listing.xml
```

## Install (production mode, as www-data, Magento in /var/www/magento)

```bash
# 0. Back up DB + code first
cd /var/www/magento
sudo -u www-data php bin/magento maintenance:enable

# 1. Copy the module (from the extracted excal-quote.tgz)
sudo mkdir -p /var/www/magento/app/code/Excal
sudo cp -r /path/to/excal-quote/app/code/Excal/Quote /var/www/magento/app/code/Excal/
sudo chown -R www-data:www-data /var/www/magento/app/code/Excal

# 2. Check nothing else already uses the "quote" storefront route (should print nothing)
grep -rl 'frontName="quote"' app/code vendor --include=routes.xml | grep -v Excal/Quote

# 3. Enable + deploy
sudo -u www-data php bin/magento module:enable Excal_Quote
sudo -u www-data php bin/magento setup:upgrade
sudo -u www-data php bin/magento setup:di:compile
sudo -u www-data php bin/magento setup:static-content:deploy -f en_GB en_US
sudo -u www-data php bin/magento cache:flush
sudo -u www-data php bin/magento maintenance:disable

# 4. Confirm
sudo -u www-data php bin/magento module:status Excal_Quote
```

The module is still **off** after install. Enter the supplier data first, then switch it on (see below).

Rollback: `sudo -u www-data php bin/magento module:disable Excal_Quote`, then run the same
`setup:upgrade` / `setup:di:compile` / `setup:static-content:deploy -f en_GB en_US` / `cache:flush` steps. The
`excal_quote*` tables are kept unless you drop them by hand.

## Configuration

Go to **Stores > Configuration > Excalibur > Quotations**.

1. **Supplier Details**: fill in every field with verified company data (see "Data the owner must provide"). Any
   field left empty prints as a red `[NOT SET: ...]` marker.
2. **Pricing**: VAT Rate (default 20) and Quotation Valid For (default 30 days).
   Delivery is read from **Stores > Configuration > Sales > Delivery Methods > Flat Rate**. That carrier must be
   enabled, with Type = *Per Order* and Price = 8.00, to give £8 per order. Magento's own default is *Per Item*
   5.00, so check this. A Flat Rate handling fee (per order) is included. If Flat Rate is disabled, delivery is
   quoted as 0.00.
3. **General**:
   - *Send Owner Copy To*: leave empty to use the supplier email.
   - *Email Sender*: a store email identity. Check **Stores > Configuration > General > Store Email Addresses** is
     set up.
   - Templates and the hourly limit (default 5, 0 = unlimited). It applies per browser session and per customer
     email address; each client IP gets 4x that (at least 20/hour) so customers behind a shared IP aren't blocked.
     The counters live in Magento's cache, so `cache:flush` resets them.
   - Then set *Enable Official Quotations* = Yes and, if wanted, *Show Link in Mini-cart* = Yes.
4. `sudo -u www-data php bin/magento cache:flush`

To customise the email wording, use **Marketing > Email Templates > Add New Template > Load Template >
"Official Quotation (customer)"**, then pick that template in the config.

The same settings from the CLI, if you prefer:

```bash
sudo -u www-data php bin/magento config:set excal_quote/supplier/legal_name "<exact legal name>"
sudo -u www-data php bin/magento config:set excal_quote/general/enabled 1
sudo -u www-data php bin/magento cache:flush
```

Cron must be installed for the nightly expiry: `sudo -u www-data crontab -l` should show the Magento cron lines
(`bin/magento cron:install` if not).

## Test checklist

- [ ] With the module disabled: no button on the basket page, and `/quote/request` gives a 404.
- [ ] Enabled: the basket page shows **Get an official quotation** under "Proceed to Checkout".
- [ ] `/quote/request` with an empty basket redirects to the basket with a notice.
- [ ] As a guest: required-field errors show client-side. Disable JS (or use curl) and confirm the server-side
      errors show and the entered values are kept.
- [ ] Invalid email or phone is rejected.
- [ ] Filling the hidden `website` field (devtools) means no quotation is created and no email is sent.
- [ ] POST without a valid form key is rejected.
- [ ] Logged in with default addresses: the form is prefilled (name, email, company, phone, addresses).
- [ ] Submitting redirects to `/quote/view/id/<48 chars>` with a success message, and the number is `EXQ-2026-0000NN`.
- [ ] Check the maths: line totals = unit ex VAT x qty; delivery = Flat Rate (£8 per order); VAT = 20% of
      (goods + delivery); total = goods + delivery + VAT.
- [ ] If a product price is entered **including** VAT in the catalogue, check the unit price shown is ex VAT.
- [ ] Every unset supplier field shows a red `[NOT SET: ...]`. After filling the config, it shows the real values.
- [ ] Print preview (Chrome, Firefox): one clean A4 page, no header/footer/toolbar, the red markers still red.
      "Save as PDF" works.
- [ ] View source has `<meta name="robots" content="NOINDEX,NOFOLLOW">`. `curl -I` shows `X-Robots-Tag: noindex`.
- [ ] Changing one character of the hash gives a 404. `/quote/view/id/short` gives a 404.
- [ ] The customer email arrives with a working link, and the owner copy arrives with reply-to = customer.
- [ ] Break SMTP temporarily: the quotation is still saved, a warning message shows, and an error is in
      `var/log/system.log`.
- [ ] Admin: **Sales > Official Quotations** lists it. Filters work. "View / print" opens the storefront page.
      Mass "Change status" to Converted/Expired works, and the page shows the banner.
- [ ] Expiry: `UPDATE excal_quote SET valid_until = CURDATE() - INTERVAL 1 DAY WHERE entity_id = <id>;`, then
      `sudo -u www-data php bin/magento cron:run --group=default` (or wait for 02:15). Status becomes expired.
- [ ] Submit 6 quotations in one session (default limit 5/hour): the 6th is refused.
- [ ] The admin role ACL "Official Quotations" / "Excalibur Quotations" restricts access.

## Data the owner must provide (nothing is pre-filled)

The module prints only what is entered. Each item below must come from the owner or official records.

1. Legal company name, exactly as registered at Companies House (the business is run by Saint Michael Web
   Services Limited; confirm the exact registered form).
2. Trading name as it should appear on the quotation (e.g. "Excalibur Labware"; confirm).
3. Companies House company number.
4. VAT registration number. Without one, the quotation must not charge VAT; ask the accountant. The VAT rate
   setting would then need to be 0.
5. Registered office address.
6. Phone number and email for quotations/orders.
7. Logo file (PNG/JPG, ideally at least 600 px wide, transparent background).
8. Bank details for BACS: bank name, account name, sort code, account number, and IBAN/BIC if international
   payments are accepted.
9. Payment terms offered to public-sector customers (e.g. the number of days net from invoice).
10. Terms and conditions text for quotations (validity, delivery times, returns, price changes, etc.).
11. Confirmation that delivery is £8 per order ex VAT for every order (Flat Rate config), or the real rule.
12. Which email address should receive owner copies.

## Notes / known limits

- Prices are a snapshot of the basket at request time: unit `base_price` ex VAT, before cart-rule discounts.
  Discounts and coupons are not shown.
- One VAT rate is applied to all goods and delivery. Zero-rated or exempt items are not handled separately.
- The quotation is in the store base currency (GBP).
- The public page is reachable by anyone who has the link. That is what you want for a quotation that gets
  forwarded to a finance team. The hash is 48 random alphanumeric characters, and the page has noindex and
  `Referrer-Policy: no-referrer`.
- "Converted" is set by hand (admin mass action). There is no automatic link to orders yet.
