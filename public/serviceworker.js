self.addEventListener('install', function(e) {
  console.log('Service Worker: Installed');
});

self.addEventListener('fetch', function(event) {
  // Você pode customizar o cache aqui
});
