# Texts

All texts go through the `messages` domain, with ICU MessageFormat. The bundle's own texts (`ui.*`, `crud.*`,
`panel.*`) are translated into 25 languages; override any of them in your application.

## Keys of a resource

| Key | Used for | Fallback when missing |
| --- | --- | --- |
| `crud.<name>.title` | the list, the menu, page titles | the name in plural, humanized: `product` → "Products" |
| `crud.<name>.new`, `.edit` | the add and edit pages | "New product", "Edit product" |
| `crud.<name>.field.<field>.name` | column headings, form labels, details | the field humanized: `createdAt` → "Created at" |
| `crud.<name>.field.<field>.help` | form help | none |
| `crud.<name>.layout.<group>.name` | tabs and fieldsets | the group humanized |
| `crud.<name>.palette.keywords` | command palette search | none |
| `crud.<name>.action.<action>` | buttons of record actions and pages | the action humanized |
| `crud.<name>.bulk.<action>` | bulk action buttons | the action humanized |
| `crud.<name>.toast.created`, `.updated`, `.deleted` | replace the bundle's toasts; get `{id}` and the saved plain values: `'Invoice {number} issued'` | the bundle's toast |

A missing translation is shown as readable text, never as the raw key. The fallbacks are a catalogue the bundle
builds from the names of your resources (`CrudDefaultTexts`). It is loaded before every translation file, so any
translation of yours wins. It exists in the default locale and in English, which other locales fall back to.

`ResourceFieldNames` services add the fields a driver knows. The Doctrine driver adds every mapped field and relation,
so the labels of a guessed form are covered too. Help texts, toasts and palette keywords have no fallback.

A menu section (`section:`) may be either a translation key or plain text. A key with no translation is shown by
its last segment, humanized (`app.nav.catalog` → "Catalog"). Board and panel labels (`label:` in the configuration)
have no fallback: use plain text or translate them.

A resource can't be named like one of the bundle's text groups (`action`, `filter`, `toast`, …, see
`AsCrud::RESERVED_NAMES`), because its texts would collide with the bundle's.

## Example

```yaml
# translations/messages+intl-icu.en.yaml
crud:
    product:
        title: Products
        new: New product
        edit: Edit product
        field:
            category: { name: Category }
            createdAt: { name: Added }
        toast:
            created: 'Product {name} added'
```

## Refusals

What is refused (a validation error, a guard, a database constraint) is shown by the `FailureMessage` service with a
translation key, `crud.error.*` by default. Pass your own key in `new CrudFailure(409, translationKey: 'app.paid')`.
