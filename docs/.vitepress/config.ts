import { defineConfig } from 'vitepress';

export default defineConfig({
  title: 'Progressive Image Bundle',
  description: 'High-performance progressive image loading for Symfony — Blurhash placeholders, zero CLS, content-addressed responsive variants.',
  base: '/progressive-image-bundle/',
  lang: 'en-US',
  cleanUrls: true,
  lastUpdated: true,

  head: [
    ['link', { rel: 'icon', href: '/progressive-image-bundle/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#2563eb' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Progressive Image Bundle' }],
    ['meta', {
      property: 'og:description',
      content: 'High-performance progressive image loading for Symfony — Blurhash placeholders, zero CLS, content-addressed responsive variants.',
    }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'Variant Pipeline', link: '/guide/variant-pipeline/overview' },
      { text: 'Cookbook', link: '/cookbook/custom-storage-backend' },
      { text: 'API Reference', link: '/api' },
      {
        text: 'Links',
        items: [
          { text: 'GitHub', link: 'https://github.com/tito10047/progressive-image-bundle' },
          { text: 'Packagist', link: 'https://packagist.org/packages/tito10047/progressive-image-bundle' },
          { text: 'Changelog', link: 'https://github.com/tito10047/progressive-image-bundle/blob/main/CHANGELOG.md' },
        ],
      },
    ],

    sidebar: [
      {
        text: 'Guide',
        items: [
          { text: 'Getting Started', link: '/guide/getting-started' },
          { text: 'Configuration Reference', link: '/guide/configuration-reference' },
          { text: 'The Twig Component', link: '/guide/twig-component' },
          { text: 'Responsive Grid & Ratios', link: '/guide/responsive-grid-and-ratios' },
          { text: 'Caching', link: '/guide/caching' },
          { text: 'Architecture', link: '/guide/architecture' },
        ],
      },
      {
        text: 'Variant Pipeline',
        items: [
          { text: 'Overview', link: '/guide/variant-pipeline/overview' },
          { text: 'Generation Strategies', link: '/guide/variant-pipeline/generation-strategies' },
          { text: 'Filters, Formats & Quality', link: '/guide/variant-pipeline/filters-formats-and-quality' },
          { text: 'Storage', link: '/guide/variant-pipeline/storage' },
        ],
      },
      {
        text: 'Cookbook',
        items: [
          { text: 'Custom Storage Backend', link: '/cookbook/custom-storage-backend' },
          { text: 'Remote (HTTP) Source Loading', link: '/cookbook/http-source-loader' },
          { text: 'On-the-Fly Resolve Route', link: '/cookbook/on-the-fly-resolve-route' },
          { text: 'Custom Image Manipulator', link: '/cookbook/custom-image-manipulator' },
          { text: 'Custom Post-Processor', link: '/cookbook/custom-post-processor' },
          { text: 'Custom Path Decorator', link: '/cookbook/custom-path-decorator' },
          { text: 'Custom Responsive URL Generator', link: '/cookbook/custom-responsive-url-generator' },
          { text: 'Custom Modifier', link: '/cookbook/custom-modifier' },
          { text: 'Async Worker Setup', link: '/cookbook/async-worker-setup' },
          { text: 'Point of Interest Cropping', link: '/cookbook/point-of-interest-cropping' },
          { text: 'Serving Behind Nginx', link: '/cookbook/serving-behind-nginx' },
        ],
      },
      { text: 'API Reference', link: '/api' },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/tito10047/progressive-image-bundle' },
    ],

    search: { provider: 'local' },

    editLink: {
      pattern: 'https://github.com/tito10047/progressive-image-bundle/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © Jozef Môstka',
    },
  },
});
