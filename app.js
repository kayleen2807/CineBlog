function setupExclusiveActive(selector, { preventDefault = false, activeClass = "active" } = {}) {
    const items = Array.from(document.querySelectorAll(selector));
    if (!items.length) return;

    items.forEach((item) => {
        item.addEventListener("click", (event) => {
            if (preventDefault) event.preventDefault();
            items.forEach((other) => other.classList.remove(activeClass));
            item.classList.add(activeClass);
        });
    });
}

function setupFlash(selector, { durationMs = 1800, preventDefault = false, flashClass = "flash" } = {}) {
    const items = Array.from(document.querySelectorAll(selector));
    if (!items.length) return;

    const timeouts = new WeakMap();

    items.forEach((item) => {
        item.addEventListener("click", (event) => {
            if (preventDefault) event.preventDefault();

            const existing = timeouts.get(item);
            if (existing) clearTimeout(existing);

            item.classList.add(flashClass);
            const timeout = setTimeout(() => {
                item.classList.remove(flashClass);
                timeouts.delete(item);
            }, durationMs);
            timeouts.set(item, timeout);
        });
    });
}

function svgPlaceholderDataUri(label, bg = "#1b2433", fg = "#edf1f7") {
    const safe = String(label || "Imagen").slice(0, 18);
    const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${bg}"/>
      <stop offset="1" stop-color="#0d1522"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" fill="url(#g)"/>
  <text x="50%" y="52%" fill="${fg}" font-family="Segoe UI, Tahoma, sans-serif" font-size="64" text-anchor="middle">
    ${safe}
  </text>
</svg>`;
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg.trim())}`;
}

function setupImageFallback() {
    const images = Array.from(document.querySelectorAll("img"));
    if (!images.length) return;

    images.forEach((img) => {
        img.addEventListener("error", () => {
            if (img.dataset.fallbackApplied) return;
            img.dataset.fallbackApplied = "1";
            img.src = svgPlaceholderDataUri(img.alt || "Imagen");
            img.classList.add("img-fallback");
        });
    });
}

function setupShowMore() {
    const buttons = Array.from(document.querySelectorAll(".toggle-review"));
    if (!buttons.length) return;

    buttons.forEach((button) => {
        const card = button.closest(".card");
        const copy = card ? card.querySelector(".review-copy") : null;
        if (!copy) return;

        copy.classList.add("collapsed");

        const moreText = button.textContent.trim() || "Show More";
        const isEnglish = moreText.toLowerCase().includes("show");
        const lessText = isEnglish ? "Show Less" : "Mostrar menos";

        button.addEventListener("click", () => {
            const collapsed = copy.classList.contains("collapsed");
            copy.classList.toggle("collapsed", !collapsed);
            button.textContent = collapsed ? lessText : moreText;
        });
    });
}

setupExclusiveActive(".nav-link", { preventDefault: true });
setupFlash(".tab", { preventDefault: true, durationMs: 1600 });
setupFlash(".stat[data-stat]");
setupFlash(".metric-btn", { durationMs: 1600 });
setupExclusiveActive(".tags .tag");
setupImageFallback();
setupShowMore();
