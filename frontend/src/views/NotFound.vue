<template>
  <div class="not-found">
    <div class="container">
      <div class="content">
        <h1>404</h1>
        <h2>Página não encontrada</h2>
        <p>Desculpe, a página que você está procurando não existe.</p>
        <router-link to="/" class="btn">Voltar ao Dashboard</router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Componente de página não encontrada
</script>

<style scoped>
.not-found {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.container {
  text-align: center;
  padding: 2rem;
}

.content {
  background: white;
  padding: 3rem;
  border-radius: 8px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

h1 {
  font-size: 4rem;
  color: #667eea;
  margin: 0;
}

h2 {
  color: #333;
  margin-top: 1rem;
}

p {
  color: #666;
  margin: 1rem 0 2rem 0;
}

.btn {
  display: inline-block;
  padding: 0.75rem 2rem;
  background: #667eea;
  color: white;
  text-decoration: none;
  border-radius: 4px;
  transition: background 0.3s;
}

.btn:hover {
  background: #5568d3;
}
</style>
