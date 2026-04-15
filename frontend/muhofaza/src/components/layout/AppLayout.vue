<template>
  <v-layout>
    <AppSidebar />
    <v-main style="background: #F5F7FA; min-height: 100vh;">
      <AppHeader />
      <v-container fluid class="pa-6">
        <router-view />
      </v-container>
    </v-main>
  </v-layout>
</template>

<script setup>
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'
</script>
