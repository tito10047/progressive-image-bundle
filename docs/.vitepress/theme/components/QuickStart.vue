<script setup lang="ts">
import { ref, shallowRef, onMounted } from 'vue';
import { codeToHtml } from 'shiki';

const tabs = [
  {
    key: 'install',
    label: '1. Install',
    lang: 'bash',
    code: 'composer require tito10047/progressive-image-bundle',
  },
  {
    key: 'configure',
    label: '2. Configure',
    lang: 'yaml',
    code: `# config/packages/progressive_image.yaml
progressive_image:
    resolvers:
        default:
            type: filesystem
            roots: ['%kernel.project_dir%/public']

    variant_store:
        storage: 'oneup_flysystem.variants_filesystem'`,
  },
  {
    key: 'render',
    label: '3. Render',
    lang: 'twig',
    code: `{# Fully automatic #}
<twig:pgi:Image src="{{ asset('images/hero.jpg') }}" alt="Hero" />

{# Tailwind-like responsive selectors #}
<twig:pgi:Image
    src="{{ asset('images/hero.jpg') }}"
    sizes="sm:12 md:6@landscape lg:4@square"
    alt="Responsive hero image"
/>`,
  },
];

const active = ref(tabs[0].key);
const highlighted = shallowRef<Record<string, string>>({});

onMounted(async () => {
  const entries = await Promise.all(
    tabs.map(async (tab) => {
      const html = await codeToHtml(tab.code, {
        lang: tab.lang,
        themes: { light: 'github-light', dark: 'github-dark' },
        transformers: [
          {
            pre(node) {
              node.properties.class = `${node.properties.class ?? ''} vp-code`.trim();
              delete node.properties.style;
            },
          },
        ],
      });
      return [tab.key, html] as const;
    }),
  );
  highlighted.value = Object.fromEntries(entries);
});
</script>

<template>
  <section class="quick-start">
    <div class="quick-start-inner">
      <h2 class="quick-start-title">Up and running in three steps</h2>

      <div class="quick-start-tabs" role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          role="tab"
          class="quick-start-tab"
          :class="{ active: active === tab.key }"
          :aria-selected="active === tab.key"
          @click="active = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-for="tab in tabs" :key="tab.key" v-show="active === tab.key">
        <div v-if="highlighted[tab.key]" class="language-block" v-html="highlighted[tab.key]" />
        <div v-else class="language-block">
          <pre><code>{{ tab.code }}</code></pre>
        </div>
      </div>

      <p class="quick-start-footnote">
        That's it &mdash; the bundle resolves real dimensions, computes what each
        breakpoint needs, and generates every variant on first request. Full walkthrough
        in the <a href="/guide/getting-started">Getting Started guide</a>.
      </p>
      <p class="quick-start-requirements">
        Requires <strong>PHP 8.3+</strong> and <strong>Symfony 6.4, 7.4, or 8.1+</strong>.
      </p>
    </div>
  </section>
</template>

<style scoped>
.quick-start {
  padding: 64px 24px 8px;
  border-top: 1px solid var(--vp-c-divider);
}

.quick-start-inner {
  max-width: 760px;
  margin: 0 auto;
}

.quick-start-title {
  text-align: center;
  font-size: 24px;
  font-weight: 700;
  margin: 0 0 24px;
}

.quick-start-tabs {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-bottom: 16px;
}

.quick-start-tab {
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  color: var(--vp-c-text-2);
  background: transparent;
  border: 1px solid var(--vp-c-divider);
  cursor: pointer;
  transition: all 0.15s ease;
}

.quick-start-tab:hover {
  color: var(--vp-c-text-1);
  border-color: var(--vp-c-brand-1);
}

.quick-start-tab.active {
  color: #fff;
  background: var(--pgi-gradient);
  border-color: transparent;
}

.language-block {
  border-radius: 10px;
  overflow-x: auto;
  background: var(--vp-code-block-bg);
  border: 1px solid var(--vp-c-divider);
}

.language-block pre {
  margin: 0;
  padding: 20px 22px;
}

.language-block code {
  font-family: var(--vp-font-family-mono);
  font-size: 13.5px;
  line-height: 1.7;
  color: var(--vp-c-text-1);
  white-space: pre;
}

.quick-start-footnote {
  text-align: center;
  margin-top: 20px;
  font-size: 14px;
  color: var(--vp-c-text-3);
}

.quick-start-requirements {
  text-align: center;
  margin-top: 8px;
  font-size: 12.5px;
  color: var(--vp-c-text-3);
}

.quick-start-requirements strong {
  color: var(--vp-c-text-2);
}
</style>
