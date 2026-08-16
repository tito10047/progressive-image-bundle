<script setup lang="ts">
import DefaultTheme from 'vitepress/theme';
import BrandStory from './components/BrandStory.vue';
import QuickStart from './components/QuickStart.vue';
import ComparisonSection from './components/ComparisonSection.vue';
import DomainStory from './components/DomainStory.vue';

const { Layout } = DefaultTheme;
</script>

<template>
  <Layout>
    <template #home-hero-after>
      <BrandStory />
    </template>
    <template #home-features-after>
      <QuickStart />
      <ComparisonSection />
      <DomainStory />
    </template>
  </Layout>
</template>
