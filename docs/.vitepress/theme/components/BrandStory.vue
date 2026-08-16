<script setup lang="ts">
const stats = [
  { value: '1', label: 'Twig tag to render', detail: '<twig:pgi:Image>, nothing else to wire up' },
  { value: '0', label: 'layout shift', detail: 'width/height reserved via CSS custom properties before load' },
  { value: '3', label: 'ways to generate', detail: 'sync, kernel.terminate, or async via Messenger' },
  { value: 'AVIF/WebP', label: 'content negotiation', detail: 'served automatically, original kept as fallback' },
];
</script>

<template>
  <section class="brand-story">
    <div class="brand-story-inner">
      <p class="brand-story-lede">
        Drop one Twig component into a template. Progressive Image Bundle resolves the
        source, works out every size your layout actually needs, generates
        content-addressed variants, and renders a real
        <code>&lt;picture&gt;</code> element with Blurhash placeholders and zero layout
        shift &mdash; no <code>LiipImagineBundle</code> required.
      </p>

      <div class="brand-story-stats">
        <div v-for="stat in stats" :key="stat.label" class="brand-stat">
          <div class="brand-stat-value">{{ stat.value }}</div>
          <div class="brand-stat-label">{{ stat.label }}</div>
          <div class="brand-stat-detail">{{ stat.detail }}</div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.brand-story {
  padding: 0 24px 8px;
}

.brand-story-inner {
  max-width: 1152px;
  margin: 0 auto;
}

.brand-story-lede {
  max-width: 760px;
  margin: 0 auto 40px;
  text-align: center;
  font-size: 17px;
  line-height: 1.7;
  color: var(--vp-c-text-2);
}

.brand-story-lede code {
  color: var(--vp-c-brand-1);
}

.brand-story-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1px;
  background: var(--vp-c-divider);
  border: 1px solid var(--vp-c-divider);
  border-radius: 12px;
  overflow: hidden;
}

.brand-stat {
  background: var(--vp-c-bg-soft);
  padding: 24px 20px;
  text-align: center;
}

.brand-stat-value {
  font-size: 26px;
  font-weight: 700;
  background: var(--pgi-gradient);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.brand-stat-label {
  margin-top: 4px;
  font-size: 14px;
  font-weight: 600;
  color: var(--vp-c-text-1);
}

.brand-stat-detail {
  margin-top: 6px;
  font-size: 12.5px;
  line-height: 1.5;
  color: var(--vp-c-text-3);
}

@media (max-width: 768px) {
  .brand-story-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
