/* ===== Side Panel Menu ===== */
var menuTrigger = document.querySelector('.menu-trigger');
var overlay = document.querySelector('.side-panel-overlay');

function openMenu() {
  document.body.classList.add('menu-open');
  menuTrigger.setAttribute('aria-expanded', 'true');
}
function closeMenu() {
  document.body.classList.remove('menu-open');
  menuTrigger.setAttribute('aria-expanded', 'false');
}

if (menuTrigger) {
  menuTrigger.addEventListener('click', function () {
    document.body.classList.contains('menu-open') ? closeMenu() : openMenu();
  });
}
if (overlay) {
  overlay.addEventListener('click', closeMenu);
}
document.querySelectorAll('.panel-nav-item').forEach(function (link) {
  link.addEventListener('click', closeMenu);
});

/* ===== Scrollify (Full-page Scroll) ===== */
if (typeof jQuery !== 'undefined' && typeof jQuery.scrollify !== 'undefined' && document.querySelector('.home-page')) {
  jQuery.scrollify({
    section: '.scroll-section',
    sectionName: false,
    interstitialSection: '.site-footer',
    scrollSpeed: 600,
    before: function (i) {
      var header = document.querySelector('.site-header');
      if (header) {
        header.classList.toggle('header-scrolled', i > 0);
      }
    }
  });
}

/* ===== Header Scroll Effect (子頁面) ===== */
var header = document.querySelector('.site-header');
if (header && !document.querySelector('.home-page')) {
  window.addEventListener('scroll', function () {
    header.classList.toggle('header-scrolled', window.scrollY > 60);
  }, { passive: true });
}

/* ===== Scroll-triggered Animations ===== */
var animEls = document.querySelectorAll('[data-animate]');
if (animEls.length) {
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });
  animEls.forEach(function (el) { observer.observe(el); });
}

/* ===== Classic Section Slider ===== */
var slides = document.querySelector('.classic-slides');
var prevBtn = document.querySelector('.classic-prev');
var nextBtn = document.querySelector('.classic-next');
var currentSlide = 0;

function updateSlider() {
  if (!slides) return;
  var total = slides.children.length;
  currentSlide = ((currentSlide % total) + total) % total;
  slides.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
}

if (prevBtn && nextBtn) {
  prevBtn.addEventListener('click', function () { currentSlide--; updateSlider(); });
  nextBtn.addEventListener('click', function () { currentSlide++; updateSlider(); });
  var autoplay = setInterval(function () { currentSlide++; updateSlider(); }, 4000);
  [prevBtn, nextBtn].forEach(function (btn) {
    btn.addEventListener('click', function () {
      clearInterval(autoplay);
      autoplay = setInterval(function () { currentSlide++; updateSlider(); }, 4000);
    });
  });
}

/* ===== Touch & Mouse Drag for Slider ===== */
if (slides) {
  var dragStartX = 0;
  var isDragging = false;

  /* 觸控 */
  slides.addEventListener('touchstart', function (e) { dragStartX = e.changedTouches[0].screenX; }, { passive: true });
  slides.addEventListener('touchend', function (e) {
    var diff = dragStartX - e.changedTouches[0].screenX;
    if (Math.abs(diff) > 50) {
      if (diff > 0) { currentSlide++; } else { currentSlide--; }
      updateSlider();
    }
  });

  /* 滑鼠拖曳 */
  slides.addEventListener('mousedown', function (e) {
    isDragging = true;
    dragStartX = e.clientX;
    slides.style.transition = 'none';
  });
  slides.addEventListener('mousemove', function (e) {
    if (!isDragging) return;
    e.preventDefault();
  });
  document.addEventListener('mouseup', function (e) {
    if (!isDragging) return;
    isDragging = false;
    slides.style.transition = 'transform 0.65s ease';
    var diff = dragStartX - e.clientX;
    if (Math.abs(diff) > 60) {
      if (diff > 0) { currentSlide++; } else { currentSlide--; }
    }
    updateSlider();
  });

  /* 防止拖曳時選取圖片 */
  slides.addEventListener('dragstart', function (e) { e.preventDefault(); });
}
