# Design

## Context

EasyAdmin renders a `BooleanField` in the index as a switch that sends an AJAX
`PATCH` to the field's `data-toggle-url` on the `change` event. The vendor
template has no confirmation hook, and the field's form options are not rendered
on the toggle input.

## Goals / Non-Goals

**Goals:**

- Show the Telegram Link state in the Users list without opening the card.
- Prevent an accidental Enabled toggle in the list.

**Non-Goals:**

- Changing the user card's existing Telegram status (already specified).
- Adding confirmations to other boolean fields.
- Editing the Telegram link from the Users list.

## Decisions

- **Virtual boolean field.** Add `BooleanField::new('telegramLinked')` with
  `renderAsSwitch(false)` and `onlyOnIndex()`. Symfony's PropertyAccess resolves
  the field through the existing `User::isTelegramLinked()`, and the vendor
  template renders the same Yes/No badge as the Audit Event success field. The
  user card keeps the richer "Linked as @name" field.
- **Template override with a custom option.** Copy the vendor boolean field
  template to `templates/bundles/EasyAdminBundle/crud/field/boolean.html.twig`
  and add an optional `onclick="return confirm(...)"` when the field has the
  `confirm` custom option. The option is set only on the Enabled field in
  `UserCrudController`.
- **Cancel prevents the AJAX toggle.** Returning `false` from the checkbox
  `onclick` cancels the default toggle before the `change` event, so EasyAdmin's
  listener never sends the `PATCH`; the switch visually stays in its previous
  position. No custom JS file is needed.
- **Translation.** The confirmation message is a normal translated string, so
  the English and Russian admin UIs get their own wording.
- **Settings switch.** The settings form is a regular form, so the web intake
  checkbox gets an inline `onclick="return confirm(...)"` through the form
  widget's `attr`; cancelling prevents the checkbox from changing. The same
  `settings-switch` CSS class removes Bootstrap's left padding/margin so the
  switch aligns with the card text.

## Risks / Trade-offs

- The template override is pinned to the current EasyAdmin boolean template; an
  EasyAdmin upgrade may require re-applying the small addition. The override
  keeps the original markup otherwise.
- The confirmation is client-side; the server still enforces its normal
  authorization and validation.
