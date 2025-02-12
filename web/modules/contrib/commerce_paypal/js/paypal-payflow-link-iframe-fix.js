// Prevent showing return page in iframe.
if (window.top !== window.self) {
  document.getElementsByTagName('HTML')[0].style.display = 'none';
}
