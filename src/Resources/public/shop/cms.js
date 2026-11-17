(function() {
  "use strict";
  const carousels = /* @__PURE__ */ new WeakMap();
  const SCROLL_SETTLE_MS = 100;
  const ICONS = {
    previous: '<path d="M10 3 5 8l5 5"/>',
    next: '<path d="m6 3 5 5-5 5"/>'
  };
  function initCarousels(root, labels) {
    const found = Array.from(root.querySelectorAll(".cms-carousel"));
    if (root instanceof HTMLElement && root.matches(".cms-carousel")) {
      found.unshift(root);
    }
    found.forEach((element) => initCarousel(element, labels));
  }
  function initCarousel(root, labels) {
    const existing = carousels.get(root);
    if (existing?.isIntact()) {
      return;
    }
    existing?.destroy();
    carousels.set(root, createCarousel(root, labels));
  }
  function createCarousel(root, labels) {
    const document2 = root.ownerDocument;
    const window = document2.defaultView;
    const track = root.querySelector(":scope > .cms-carousel__track");
    const slides = track === null ? [] : slidesOf(track);
    const autoplayAttribute = root.getAttribute("data-cms-autoplay");
    const isIntact = (added2) => root.querySelector(":scope > .cms-carousel__track") === track && (track === null || sameElements(slidesOf(track), slides)) && added2.every((element) => element.parentNode === root) && root.getAttribute("data-cms-autoplay") === autoplayAttribute;
    if (track === null || slides.length < 2 || window === null) {
      return { isIntact: () => isIntact([]), destroy: () => {
      } };
    }
    const listening = new AbortController();
    const { signal } = listening;
    let index = 0;
    let autoplayTimer;
    let scrollTimer;
    const holds = /* @__PURE__ */ new Set();
    const previousButton = button(document2, "cms-carousel__prev", labels.previous, ICONS.previous);
    const nextButton = button(document2, "cms-carousel__next", labels.next, ICONS.next);
    const dotList = document2.createElement("ul");
    dotList.className = "cms-carousel__dots";
    const dots = slides.map((_, i) => {
      const item = document2.createElement("li");
      const dot = button(document2, "cms-carousel__dot", labels.slide.replace("%number%", String(i + 1)));
      dot.addEventListener("click", () => goTo(i), { signal });
      item.append(dot);
      dotList.append(item);
      return dot;
    });
    const added = [previousButton, nextButton, dotList];
    root.append(...added);
    const rtl = () => window.getComputedStyle(track).direction === "rtl";
    const startOf = (element) => {
      const box = element.getBoundingClientRect();
      return rtl() ? -box.right : box.left;
    };
    const reachedEnd = () => {
      const scrollable = track.scrollWidth - track.clientWidth;
      return scrollable > 1 && Math.abs(track.scrollLeft) >= scrollable - 1;
    };
    const show = (i) => {
      index = i;
      dots.forEach((dot, j) => j === i ? dot.setAttribute("aria-current", "true") : dot.removeAttribute("aria-current"));
    };
    const goTo = (i) => {
      show(i);
      const slide = slides[i];
      if (slide !== void 0) {
        const offset = startOf(slide) - startOf(track);
        track.scrollLeft += rtl() ? -offset : offset;
      }
    };
    const next = () => goTo(index >= slides.length - 1 || reachedEnd() ? 0 : index + 1);
    const previous = () => goTo(index <= 0 ? slides.length - 1 : index - 1);
    const follow = () => {
      const start2 = startOf(track);
      let nearest = 0;
      slides.forEach((slide, i) => {
        if (Math.abs(startOf(slide) - start2) < Math.abs(startOf(slides[nearest]) - start2) - 0.5) {
          nearest = i;
        }
      });
      show(nearest);
    };
    const autoplay = Number(autoplayAttribute);
    const stopAutoplay = () => {
      window.clearInterval(autoplayTimer);
      autoplayTimer = void 0;
    };
    const destroy = () => {
      listening.abort();
      stopAutoplay();
      window.clearTimeout(scrollTimer);
      added.forEach((element) => element.remove());
    };
    const play = () => {
      if (autoplay > 0 && Number.isFinite(autoplay) && holds.size === 0 && autoplayTimer === void 0) {
        autoplayTimer = window.setInterval(() => root.isConnected ? next() : destroy(), autoplay);
      }
    };
    const hold = (reason) => {
      holds.add(reason);
      stopAutoplay();
    };
    const release = (reason) => {
      holds.delete(reason);
      play();
    };
    previousButton.addEventListener("click", previous, { signal });
    nextButton.addEventListener("click", next, { signal });
    track.addEventListener(
      "scroll",
      () => {
        window.clearTimeout(scrollTimer);
        scrollTimer = window.setTimeout(follow, SCROLL_SETTLE_MS);
      },
      { passive: true, signal }
    );
    root.addEventListener("pointerenter", () => hold("pointer"), { signal });
    root.addEventListener("pointerleave", () => release("pointer"), { signal });
    root.addEventListener("focusin", () => hold("focus"), { signal });
    root.addEventListener(
      "focusout",
      (event) => {
        if (!(event.relatedTarget instanceof Node && root.contains(event.relatedTarget))) {
          release("focus");
        }
      },
      { signal }
    );
    show(0);
    if (root.contains(document2.activeElement)) {
      hold("focus");
    }
    play();
    return { isIntact: () => isIntact(added), destroy };
  }
  function slidesOf(track) {
    return Array.from(track.children).filter((child) => child.classList.contains("cms-carousel__slide"));
  }
  function sameElements(a, b) {
    return a.length === b.length && a.every((element, i) => element === b[i]);
  }
  function button(document2, className, label, icon) {
    const element = document2.createElement("button");
    element.type = "button";
    element.className = className;
    element.setAttribute("aria-label", label);
    if (icon !== void 0) {
      element.innerHTML = `<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${icon}</svg>`;
    }
    return element;
  }
  const ENGLISH = { previous: "Previous slide", next: "Next slide", slide: "Slide %number%" };
  function start(document2, script) {
    const data = script instanceof HTMLElement ? script.dataset : {};
    const labels = {
      previous: data.cmsLabelPrevious || ENGLISH.previous,
      next: data.cmsLabelNext || ENGLISH.next,
      slide: data.cmsLabelSlide || ENGLISH.slide
    };
    const onLoad = () => initCarousels(document2, labels);
    const onRender = (event) => {
      if (event.target instanceof Element) {
        initCarousels(event.target, labels);
      }
    };
    document2.addEventListener("cms:render", onRender);
    if (document2.readyState === "loading") {
      document2.addEventListener("DOMContentLoaded", onLoad, { once: true });
    } else {
      onLoad();
    }
    return () => {
      document2.removeEventListener("cms:render", onRender);
      document2.removeEventListener("DOMContentLoaded", onLoad);
    };
  }
  start(document, document.currentScript);
})();
