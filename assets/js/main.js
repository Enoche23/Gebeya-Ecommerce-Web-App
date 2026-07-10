// assets/js/main.js

console.log("Gebeya JS loaded");

/* PREVENT DOUBLE FORM SUBMISSION (Stops duplicate image uploads) */
document.addEventListener("submit", function (e) {
  const form = e.target;

  if (!form.classList.contains("no-disable")) {
    const btn = form.querySelector("button[type='submit']");
    if (btn) {
      btn.disabled = true;
      btn.dataset.originalText = btn.innerText;
      btn.innerText = "Submitting...";
    }
  }
});

/* Restore buttons if user navigates back */
window.addEventListener("pageshow", function () {
  document.querySelectorAll("button[type='submit']").forEach(btn => {
    if (btn.disabled && btn.dataset.originalText) {
      btn.disabled = false;
      btn.innerText = btn.dataset.originalText;
    }
  });
});


/* UI TOGGLES (Search + Bag + Nav) */
document.addEventListener("DOMContentLoaded", function () {

  /* Nav Hamburger */
  const toggleNav = document.getElementById("toggleNav");
  const navLinks = document.getElementById("navLinks");

  if (toggleNav && navLinks) {
    toggleNav.addEventListener("click", function (e) {
      e.stopPropagation();
      navLinks.classList.toggle("open");
    });

    window.addEventListener("resize", function() {
      if (window.innerWidth > 900 && navLinks.classList.contains("open")) {
        navLinks.classList.remove("open");
      }
    });
  }

  /* Search panel */
  const toggleSearch = document.getElementById("toggleSearch");
  const searchPanel = document.getElementById("searchPanel");

  if (toggleSearch && searchPanel) {
    toggleSearch.addEventListener("click", function (e) {
      e.stopPropagation();
      searchPanel.classList.toggle("open");

      // Auto-focus search input when opened
      if (searchPanel.classList.contains("open")) {
        const input = searchPanel.querySelector("input[name='search']");
        if (input) setTimeout(() => input.focus(), 200);
      }
    });
  }

/* Bag menu */
const toggleBag = document.getElementById("toggleBag");
const bagMenu = document.getElementById("bagMenu");

if (toggleBag && bagMenu) {

  toggleBag.addEventListener("click", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const isOpen = !bagMenu.hasAttribute("hidden");

    // Close everything first
    bagMenu.setAttribute("hidden", "");

    // If it was closed, open it
    if (!isOpen) {
      bagMenu.removeAttribute("hidden");
    }
  });

  // Close when clicking outside
  document.addEventListener("click", function (e) {
    if (!bagMenu.contains(e.target) && e.target !== toggleBag) {
      bagMenu.setAttribute("hidden", "");
    }
  });

  // Close with ESC
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      bagMenu.setAttribute("hidden", "");
    }
  });

  // Prevent inside clicks from closing it
  bagMenu.addEventListener("click", function (e) {
    e.stopPropagation();
  });
}

  /* Close on outside click */
  document.addEventListener("click", function () {
    if (bagMenu && !bagMenu.hasAttribute("hidden")) {
      bagMenu.setAttribute("hidden", "");
    }
    if (searchPanel && searchPanel.classList.contains("open")) {
      searchPanel.classList.remove("open");
    }
  });

  /* Close on ESC */
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      if (bagMenu && !bagMenu.hasAttribute("hidden")) {
        bagMenu.setAttribute("hidden", "");
      }
      if (searchPanel && searchPanel.classList.contains("open")) {
        searchPanel.classList.remove("open");
      }
    }
  });

  /* Stop clicks inside panels from closing them */
  if (bagMenu) {
    bagMenu.addEventListener("click", function (e) {
      e.stopPropagation();
    });
  }
  if (searchPanel) {
    searchPanel.addEventListener("click", function (e) {
      e.stopPropagation();
    });
  }

});

/* HERO SLIDER FUNCTIONALITY */
document.addEventListener("DOMContentLoaded", function () {

  const slides = document.querySelectorAll(".hero-slide");
  const next = document.getElementById("nextSlide");
  const prev = document.getElementById("prevSlide");

  if (!slides.length) return;

  let current = 0;

  function showSlide(index){
    slides.forEach(s => s.classList.remove("active"));
    slides[index].classList.add("active");
  }

  function nextSlide(){
    current = (current + 1) % slides.length;
    showSlide(current);
  }

  function prevSlide(){
    current = (current - 1 + slides.length) % slides.length;
    showSlide(current);
  }

  if (next) next.addEventListener("click", nextSlide);
  if (prev) prev.addEventListener("click", prevSlide);

  // Auto slide every 5 seconds
  setInterval(nextSlide, 5000);
});
