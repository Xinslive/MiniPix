const button = document.querySelector('#scroll-to-top');
button.addEventListener('click', () => {
  const scrollTop = window.scrollY;
  const scrollStep = Math.PI / (500 / 15);
  const cosParameter = scrollTop / 2;
  let scrollCount = 0;
  let scrollMargin;
  const scrollInterval = setInterval(() => {
    if (window.scrollY != 0) {
      scrollCount = scrollCount + 1;
      scrollMargin = cosParameter - cosParameter * Math.cos(scrollCount * scrollStep);
      window.scrollTo(0, (scrollTop - scrollMargin));
    } else clearInterval(scrollInterval);
  }, 15);
});