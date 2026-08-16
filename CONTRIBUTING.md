# Contributing

## Backward compatibility / SemVer policy

Version 2.0 is currently **experimental** and unreleased (see the README) — this policy
describes the commitment that will apply once it's tagged `2.0.0`, and is worth following
even now to avoid unnecessary churn.

### What's covered by SemVer

A breaking change to any of the following requires a major version bump:

- **The Twig-facing surface**: `<twig:pgi:Image>`'s props, the `pgi_filter()` Twig
  function's signature, and the shape of `progressive_image.*` bundle configuration
  (renaming/removing an existing key, changing its accepted values incompatibly).
- **Console commands**: `progressive-image:generate-custom-css`,
  `progressive-image:variant:warm`, `progressive-image:variant:remove` — their argument/
  option names and meaning.
- **The Variant context's public ports** (`src/Variant/Domain/Port/*` and
  `src/Variant/Application/Port/*`) — `ImageManipulator`, `SourceReader`, `VariantStorage`,
  `GenerationLock`, `Lock`, `Clock`, `PostProcessor`, `GenerationDispatcher`,
  `OriginalUrlResolver`, `PendingUrlBuilder`, `UrlSigner` — these are the bundle's
  documented extension points (see the [Cookbook](/cookbook/custom-storage-backend));
  anyone implementing one has effectively depended on its method signatures.
- **The extension-facing interfaces outside `Variant/`**: `ModifierInterface`,
  `FilterModifierInterface`, `LoaderInterface`, `PathResolverInterface`,
  `ResponsiveImageUrlGeneratorInterface`.
- **The content-addressed URL layout itself** (`VariantPath`'s
  `{format}/{shard}/{id}/{source}.{ext}` shape, and `variant_store.public_url_prefix`'s
  default) — changing this invalidates every previously generated variant's URL for anyone
  relying on the path shape externally (e.g. a CDN rule, an nginx `location` block).

### What's explicitly internal (no BC promise)

- Everything not listed above, including but not limited to: concrete classes not behind a
  listed port (`FlysystemVariantStorage`, `InterventionImageManipulator`,
  `VariantSpecFactory`, `GenerateVariantHandler`, `ResolveVariantUrlHandler`,
  `ResolveFilterUrlHandler`, every class under `src/Variant/Infrastructure/*`), private/
  protected methods on any class, the exact rendered HTML structure inside
  `templates/components/Image.html.twig` (only the documented props/output *behavior* is
  covered, not the markup), and the `VariantId` HMAC hash schema
  (`VariantIdHasher::HASH_SCHEMA_VERSION` — bumping it deliberately invalidates the whole
  variant store on upgrade, and is called out in the CHANGELOG when it happens, but is not
  itself a BC break in the SemVer sense since no public API signature changes).
- Anything under `tests/` — including the shared contract test cases
  (`VariantStorageContractTestCase`, `SourceReaderContractTestCase`) referenced from the
  Cookbook. They're a testing aid, not a versioned API; a new required test method may be
  added in a minor release to close a gap in the contract.

### Deprecation policy

A public API element (per the list above) that needs to change gets deprecated (PHP
`#[\Deprecated]` attribute or a docblock `@deprecated` tag, plus a CHANGELOG entry with a
migration path) for at least one minor release before removal in the next major.

## Development

See [Architecture](docs/guide/architecture.md) for how the codebase is laid out before
making a change. In short:

- New Variant-context code goes under `src/Variant/{Domain,Application,Infrastructure}`,
  respecting the inward-only dependency direction `deptrac.yaml` enforces in CI.
- Every change lands via TDD: a failing test first, then the implementation.
- Before committing: `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`,
  `vendor/bin/deptrac analyse`, and `vendor/bin/php-cs-fixer fix --dry-run --diff` should
  all be clean.
- User-facing behavior changes need a matching docs page (`docs/guide/` or
  `docs/cookbook/`) — a feature without documentation isn't considered done.
