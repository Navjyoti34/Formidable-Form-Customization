const toggleLink = document.getElementById('toggle-newsletter-optins');
const newsletterSection = document.getElementById('newsletter-section');

toggleLink.addEventListener('click', (event) => {
    event.preventDefault();
  if (newsletterSection.style.display === 'none') {
    newsletterSection.style.display = 'block';
  } else {
    newsletterSection.style.display = 'none';
  }
});