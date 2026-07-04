<template>
  <div :class="['app', darkMode ? 'dark' : 'light']">
    <nav class="navbar">
      <div class="nav-brand">
        <span class="logo">🚗</span>
        <span class="brand-name">UnfallAtlas <span class="brand-de">DE</span></span>
      </div>
      <div class="nav-links">
        <RouterLink to="/">Dashboard</RouterLink>
        <RouterLink to="/accidents">Accidents</RouterLink>
        <RouterLink to="/regions">Regions</RouterLink>
        <RouterLink to="/stats">Statistics</RouterLink>
        <RouterLink to="/api-explorer">API Explorer</RouterLink>
      </div>
      <button @click="darkMode = !darkMode; localStorage.setItem('theme', darkMode ? 'dark' : 'light')" class="btn-ghost theme-toggle">
        {{ darkMode ? '☀️' : '🌙' }}
      </button>
    </nav>
    <main class="main-content">
      <RouterView />
    </main>
    <footer class="footer">
      <span>Data: Statistische Ämter des Bundes und der Länder</span>
      <span class="sep">|</span>
      <span>License: <a href="https://www.govdata.de/dl-de/by-2-0" target="_blank">dl-de/by-2-0</a></span>
      <span class="sep">|</span>
      <span>Source: <a href="https://unfallatlas.statistikportal.de/" target="_blank">Unfallatlas</a></span>
    </footer>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView } from 'vue-router'

const darkMode = ref(localStorage.getItem('theme') !== 'light')
</script>

<style scoped>
.app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--bg);
}

.navbar {
  display: flex;
  align-items: center;
  gap: 2rem;
  padding: 0 2rem;
  height: 60px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--text);
  white-space: nowrap;
  text-decoration: none;
}

.logo {
  font-size: 1.4rem;
}

.brand-de {
  color: var(--accent);
}

.nav-links {
  display: flex;
  gap: 1.5rem;
  flex: 1;
}

.nav-links a {
  color: var(--text-muted);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: color 0.2s;
  padding: 0.25rem 0;
  border-bottom: 2px solid transparent;
  transition: color 0.2s, border-color 0.2s;
}

.nav-links a:hover {
  color: var(--text);
}

.nav-links a.router-link-active {
  color: var(--accent);
  border-bottom-color: var(--accent);
}

.theme-toggle {
  font-size: 1rem;
  padding: 0.3rem 0.6rem;
}

.main-content {
  flex: 1;
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
  width: 100%;
  box-sizing: border-box;
}

.footer {
  padding: 1rem 2rem;
  background: var(--surface);
  border-top: 1px solid var(--border);
  display: flex;
  gap: 1rem;
  font-size: 0.8rem;
  color: var(--text-muted);
  flex-wrap: wrap;
  align-items: center;
}

.footer a {
  color: var(--accent2);
  text-decoration: none;
}

.footer a:hover {
  color: var(--accent);
}

.sep {
  color: var(--border);
}

@media (max-width: 768px) {
  .navbar {
    flex-wrap: wrap;
    height: auto;
    padding: 0.75rem 1rem;
    gap: 1rem;
  }
  .nav-links {
    order: 3;
    width: 100%;
    overflow-x: auto;
    padding-bottom: 0.25rem;
  }
  .main-content {
    padding: 1rem;
  }
}
</style>
