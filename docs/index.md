---
layout: home

hero:
  name: Progressive Image Bundle
  text: Responsive images for Symfony, done right.
  tagline: Blurhash placeholders, zero CLS, content-addressed variant generation — one Twig component, no LiipImagine required.
  image:
    src: /logo.svg
    alt: Progressive Image Bundle
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: Twig Component
      link: /guide/twig-component
    - theme: alt
      text: View on GitHub
      link: https://github.com/tito10047/progressive-image-bundle

features:
  - icon: 🎨
    title: Blur & Error Placeholders
    details: Every image renders behind a Blurhash placeholder decoded client-side, with a built-in error overlay if the source can't be resolved.
  - icon: 🖼️
    title: Responsive via <picture>
    details: Tailwind-like breakpoint selectors ("sm:12 md:6@landscape lg:4@square") compile to a real <picture> element with per-breakpoint srcset/sizes.
  - icon: ⚙️
    title: Content-Addressed Variants
    details: Every generated size is identified by an HMAC hash of its source + spec — the same request from any worker produces the same file, with no coordination needed.
  - icon: 🚦
    title: Three Generation Strategies
    details: Generate synchronously in the request, deferred on kernel.terminate, or asynchronously via Messenger — same pipeline, different "when".
  - icon: 🎯
    title: Zero CLS
    details: Aspect ratio and width are reserved via CSS custom properties before the image ever loads, so nothing jumps.
  - icon: 🧩
    title: Built to Be Extended
    details: Swap the storage backend, image engine, post-processors, path resolution or URL generation — every seam is a Symfony service/interface.
---
