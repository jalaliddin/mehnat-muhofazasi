<template>
  <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-5">
    <div class="d-flex align-center justify-space-between">
      <div>
        <div class="text-body-2 mb-1" style="color: #666">{{ title }}</div>
        <div class="text-h4 font-weight-bold" :style="{ color: color || '#0D1B2A' }">{{ value }}</div>
        <div v-if="subtitle" class="text-caption mt-1" style="color: #999">{{ subtitle }}</div>
      </div>
      <v-avatar :color="iconBg || 'primary'" size="56" rounded="lg">
        <v-icon :icon="icon" color="white" size="28" />
      </v-avatar>
    </div>
  </v-card>
</template>

<script setup>
defineProps(['title', 'value', 'subtitle', 'icon', 'color', 'iconBg'])
</script>
