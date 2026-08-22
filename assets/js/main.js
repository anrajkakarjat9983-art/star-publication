/* Star Publication — UI interactions */
(function () {
  "use strict";

  /* Header shadow on scroll + back-to-top */
  const header = document.getElementById("header");
  const toTop = document.getElementById("toTop");
  function onScroll() {
    if (header) header.classList.toggle("scrolled", window.scrollY > 8);
    if (toTop) toTop.classList.toggle("show", window.scrollY > 500);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();
  if (toTop) toTop.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));

  /* Mobile nav */
  const toggle = document.getElementById("navToggle");
  const nav = document.getElementById("nav");
  if (toggle && nav) {
    toggle.addEventListener("click", () => {
      const open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", String(open));
    });
    nav.querySelectorAll("a").forEach((a) =>
      a.addEventListener("click", () => {
        nav.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
      })
    );
  }

  /* Reveal-on-scroll */
  const revealEls = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && revealEls.length) {
    const io = new IntersectionObserver(
      (entries) => entries.forEach((e) => {
        if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
      }),
      { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
    );
    revealEls.forEach((el) => io.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add("in"));
  }

  /* Animated counters */
  const counters = document.querySelectorAll("[data-count]");
  function animateCount(el) {
    const target = parseInt(el.dataset.count, 10) || 0;
    const dur = 1600;
    const start = performance.now();
    function tick(now) {
      const p = Math.min((now - start) / dur, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased).toLocaleString("en-IN");
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  if ("IntersectionObserver" in window && counters.length) {
    const cio = new IntersectionObserver(
      (entries) => entries.forEach((e) => {
        if (e.isIntersecting) { animateCount(e.target); cio.unobserve(e.target); }
      }),
      { threshold: 0.5 }
    );
    counters.forEach((c) => cio.observe(c));
  } else {
    counters.forEach(animateCount);
  }

  /* FAQ — close others when one opens */
  const faqs = document.querySelectorAll(".faq-item");
  faqs.forEach((item) =>
    item.addEventListener("toggle", () => {
      if (item.open) faqs.forEach((o) => { if (o !== item) o.open = false; });
    })
  );

  /* Footer year */
  const year = document.getElementById("year");
  if (year) year.textContent = new Date().getFullYear();

  /* Toast for contact-form redirect status (?sent=1 / ?sent=0#contact) */
  const toast = document.getElementById("toast");
  if (toast) {
    const params = new URLSearchParams(window.location.search);
    const sent = params.get("sent");
    if (sent !== null) {
      toast.textContent = sent === "1"
        ? "Thank you! Your enquiry has been received. We will contact you soon."
        : "Sorry, something went wrong. Please try again or call us directly.";
      toast.classList.add("show", sent === "1" ? "success" : "error");
      window.history.replaceState({}, "", window.location.pathname + window.location.hash);
      setTimeout(() => toast.classList.remove("show"), 5200);
    }
  }

  /* Auto-hide PHP flash alerts on auth pages */
  document.querySelectorAll("[data-autoclose]").forEach((el) => {
    setTimeout(() => {
      el.style.transition = "opacity .5s ease";
      el.style.opacity = "0";
      setTimeout(() => el.remove(), 500);
    }, 6000);
  });
})();
