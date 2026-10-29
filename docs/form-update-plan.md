# Approved form update scope (1.12.0)

**Historical approved scope, implemented locally:** the original tattoo and piercing requirements are retained below for traceability; current behavior belongs in the [canonical documentation](README.md) and execution evidence is tracked in [Pending work](backlog.md#form-guardian-01--reduced-contact-data-and-guardian-authorization).

- Remove address, city and postal code from forms, storage, administration and future backups. Migrate existing columns explicitly and discard retired fields when restoring supported historical backups.
- Calculate minority as under 18 on the submission date in the WordPress site timezone. Reject unchecked minority declarations on the client and server when the birth date indicates a minor.
- When checked, state explicitly in French, German, Italian, English and Spanish that the minor must attend the appointment with the paper authorization completed and signed by their legal guardian.
- Add a General settings tab with five independent WordPress media selectors, one for each of those languages, shared by both service forms. Accept PDF, JPG/JPEG, PNG, WebP and AVIF; store attachment IDs and validate types server-side.
- Resolve the document by the active form language, including regional locales and Polylang slugs. Never silently substitute a document in a different language. If unavailable, show translated instructions to contact the studio; submission remains permitted after the other checks.
- Provide a shared accessible mobile default design with transparent container, local Roboto, black readable text, rounded controls, visible keyboard focus and usable checkboxes. Preserve custom design options.
- Preserve PHP 7.4, nonce checks, capabilities, signed results, consent evidence and duplicate-submit protection.
- Adapt schema migration, historical restore, translations, packaging and focused PHP/browser/native WordPress checks before considering the work complete. Existing local, Drive and email backup copies require a separately recorded disposal assessment.

**Status:** implemented locally. See the canonical documentation and FORM-GUARDIAN-01 for validation evidence and remaining production/content checks.
